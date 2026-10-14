<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Warehouse\Repository\WarehouseRepository;
use App\Domain\Warehouse\UseCases\Dtos\ChangeStockDto;

final readonly class ChangeStock {
    public function __construct(private ProductRepository $products, private WarehouseRepository $warehouse) {}

    public function execute(int $productId, ChangeStockDto $dto, int $userId, bool $add, bool $canChooseStorage): array {
        $this->warehouse->requireImported();
        if (!$canChooseStorage && $dto->storage !== 2) throw new \DomainException('Для вашего пользователя доступен только склад Цех.');
        $quantity = Decimal::fromString($dto->quantity, 3);
        if ($quantity->isZero() || $quantity->isNegative()) throw new \DomainException('Количество должно быть больше нуля.');
        if ($quantity->scaledValue() > 99999999999) throw new \DomainException('Слишком большое количество.');
        $date = $dto->shift !== null && $add ? ($dto->date ?? throw new \DomainException('Укажите дату изготовления.')) : null;
        if ($date !== null && (\DateTimeImmutable::createFromFormat('!Y-m-d', $date)?->format('Y-m-d') !== $date)) throw new \DomainException('Некорректная дата изготовления.');
        $key = 'stock:'.$userId.':'.$dto->operationId;
        $payload = ['productId'=>$productId,'quantity'=>$quantity->format(),'add'=>$add,'storage'=>$dto->storage,'comment'=>$dto->comment,'shift'=>$add ? $dto->shift : null,'date'=>$date];
        return $this->products->transactional(function () use ($productId,$dto,$userId,$add,$quantity,$date,$key,$payload): array {
            $product = $this->products->findForUpdate($productId) ?? throw new \DomainException('Продукция не найдена.');
            if ($existing = $this->warehouse->operation($key)) {
                $stored = json_decode($existing['payload'], true, 512, JSON_THROW_ON_ERROR);
                if (($stored['request'] ?? null) !== $payload) throw new \DomainException('Ключ операции уже использован для других данных.');
                return ['id'=>(int)$existing['id'],'warnings'=>$stored['warnings'] ?? []];
            }
            $now = new \DateTimeImmutable();
            $product->changeStock($dto->storage, $add ? $quantity : Decimal::zero(3)->subtract($quantity), $now);
            $warnings = $date !== null ? $this->warehouse->shiftWarnings($product->getParent()->getId(), $dto->shift, $date) : [];
            $id = $this->warehouse->record($product,$userId,$add ? 'added' : 'deleted',$quantity,$dto->storage,$dto->comment,$add ? $dto->shift : null,$date === null ? null : $date.' 23:59:00',$key,['request'=>$payload]);
            if ($date !== null) $warnings = array_merge($warnings, $this->warehouse->consumeMaterials($product,$quantity,$dto->shift,$date.' 23:59:00',$userId,$id));
            $this->warehouse->setOperationWarnings($id,$payload,$warnings);
            $this->products->save($product);
            return ['id'=>$id,'warnings'=>$warnings];
        });
    }
}
