<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Warehouse\Repository\WarehouseRepository;
use App\Domain\Warehouse\UseCases\Dtos\CreateProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\CompleteProductionDto;
use App\Domain\Warehouse\UseCases\Dtos\AcceptProductionDto;

final readonly class ProductionTasks {
    public function __construct(private ProductRepository $products, private WarehouseRepository $warehouse) {}

    public function create(int $groupId, CreateProductionDto $dto, int $userId): array {
        $this->warehouse->requireImported();
        return $this->products->transactional(function () use ($groupId,$dto,$userId): array {
            $group = $this->products->findForUpdate($groupId) ?? throw new \DomainException('Группа не найдена.');
            if ($group->getParent() !== null) throw new \DomainException('Укажите группу продукции.');
            $ids = [];
            $products = $this->products->findVariantsForUpdate($group);
            // Stable lock order prevents opposite selections from deadlocking.
            $lines = $dto->lines;
            usort($lines, static fn ($a,$b) => $a->productId <=> $b->productId);
            foreach ($lines as $line) {
                if (isset($ids[$line->productId])) throw new \DomainException('Продукт повторяется в задании.');
                $product = $products[$line->productId] ?? throw new \DomainException('Продукция не найдена в выбранной группе.');
                if ($product->getParent()?->getId() !== $groupId) throw new \DomainException('Продукт не относится к выбранной группе.');
                $quantity = Decimal::fromString($line->quantity,3);
                if ($quantity->isNegative() || $quantity->isZero()) throw new \DomainException('Количество задания должно быть положительным.');
                $ids[$line->productId] = $this->warehouse->createTask($line->productId,$quantity,$dto->press,$dto->date,$dto->comment,$userId,'task:'.$userId.':'.$dto->operationId.':'.$line->productId);
            }
            return array_values($ids);
        });
    }

    public function complete(int $id, CompleteProductionDto $dto, int $userId): int {
        $this->warehouse->requireImported();
        return $this->products->transactional(function () use ($id,$dto,$userId): int {
            $task = $this->warehouse->taskForUpdate($id) ?? throw new \DomainException('Задача не найдена.');
            $key = 'production:'.$userId.':'.$dto->operationId;
            if ($entry = $this->warehouse->entryByKey($key)) {
                if ((int)$entry['task_id'] !== $id || !Decimal::fromString($entry['quantity'],3)->equals(Decimal::fromString($dto->quantity,3)) || (int)$entry['shift_id'] !== $dto->shift || substr($entry['created_at'],0,10) !== $dto->date) throw new \DomainException('Ключ операции уже использован.');
                return (int)$entry['id'];
            }
            $quantity = Decimal::fromString($dto->quantity,3);
            $done = $task->completePart($quantity, $this->warehouse->completed($id));
            return $this->warehouse->addEntry($task,$quantity,$dto->shift,$dto->date,$userId,$key,$done);
        });
    }

    public function accept(int $id, AcceptProductionDto $dto, int $userId, bool $canChooseStorage): array {
        $this->warehouse->requireImported();
        if (!$canChooseStorage && $dto->storage !== 2) throw new \DomainException('Доступен только склад Цех.');
        return $this->products->transactional(function () use ($id,$dto,$userId): array {
            $entry = $this->warehouse->entryForUpdate($id) ?? throw new \DomainException('Выработка не найдена.');
            $key = 'accept:'.$userId.':'.$dto->operationId;
            if ($operation = $this->warehouse->operation($key)) {
                $payload = json_decode($operation['payload'],true,512,JSON_THROW_ON_ERROR);
                if (($payload['productionEntryId'] ?? null) !== $id || (int)$operation['storage'] !== $dto->storage || $operation['comment'] !== $dto->comment || ($dto->quantity !== null && !Decimal::fromString($operation['quantity'],3)->equals(Decimal::fromString($dto->quantity,3)))) throw new \DomainException('Ключ операции уже использован.');
                return ['warnings'=>$payload['warnings'] ?? [],'alreadyAccepted'=>true];
            }
            if ($entry['quantity'] === null || !$entry['product_id']) throw new \DomainException('Запись не является выработкой.');
            if ($entry['accepted_by'] !== null) return ['warnings'=>[], 'alreadyAccepted'=>true];
            $product = $this->products->findForUpdate((int)$entry['product_id']) ?? throw new \DomainException('Продукция не найдена.');
            $quantity = Decimal::fromString($dto->quantity ?? $entry['quantity'],3);
            if ($quantity->isZero() || $quantity->isNegative()) throw new \DomainException('Количество выработки должно быть положительным.');
            $product->changeStock($dto->storage,$quantity,new \DateTimeImmutable());
            $date = $entry['created_at'] ?? throw new \DomainException('Не указана дата выработки.');
            $shift = (int)$entry['press_id'] === 0 ? 111 : 200 + (int)$entry['press_id'] * 10 + (int)$entry['shift_id'];
            $historyId = $this->warehouse->record($product,$userId,'added',$quantity,$dto->storage,$dto->comment,$shift,substr($date,0,10).' 23:59:00',$key,['productionEntryId'=>$id],$entry['task_id'] ? (int)$entry['task_id'] : null);
            $warnings = $this->warehouse->consumeMaterials($product,$quantity,$shift,substr($date,0,10).' 23:59:00',$userId,$historyId);
            $this->warehouse->setAcceptanceWarnings($historyId,$id,$warnings);
            $this->warehouse->acceptPart($entry,$quantity,$userId);
            $this->products->save($product);
            return ['warnings'=>$warnings,'alreadyAccepted'=>false];
        });
    }

    public function closeCover(int $id, int $userId): void {
        $this->warehouse->requireImported();
        $this->products->transactional(function () use ($id,$userId): void {
            $entry = $this->warehouse->entryForUpdate($id) ?? throw new \DomainException('Выработка не найдена.');
            if ($entry['accepted_by'] !== null) return;
            if ($entry['quantity'] === null || !$entry['product_id']) throw new \DomainException('Запись не является выработкой.');
            $this->warehouse->acceptPart($entry,Decimal::zero(3),$userId,true);
        });
    }
}
