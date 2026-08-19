<?php declare(strict_types=1);

namespace App\Domain\Warehouse\UseCases;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Repository\ProductRepository;
use App\Domain\Warehouse\UseCases\Dtos\SetMinimumDto;

final readonly class SetMinimum {
    public function __construct(private ProductRepository $products) {}
    public function execute(int $id, SetMinimumDto $dto, int $userId): array {
        return $this->products->transactional(function () use ($id,$dto,$userId): array {
            $product = $this->products->findForUpdate($id) ?? throw new \DomainException('Продукция не найдена.');
            if ($product->getEditVersion() !== $dto->version) throw new \App\Domain\Product\Exceptions\ProductVersionConflict();
            $before = $product->getSettings();
            $now = new \DateTimeImmutable();
            $product->setMinimum($dto->quantity === '' ? null : Decimal::fromString($dto->quantity,3), $now);
            $this->products->save($product);
            if ($before !== $product->getSettings()) $this->products->recordChange($product,$userId,$before,$now);
            return ['quantity'=>$product->getSettings()['minQuantity'] ?? '', 'version'=>$product->getEditVersion()];
        });
    }
}
