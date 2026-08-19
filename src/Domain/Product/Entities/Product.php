<?php declare(strict_types=1);

namespace App\Domain\Product\Entities;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Exceptions\ProductVersionConflict;
use App\Domain\Product\UseCases\Dtos\SaveProductDto;
use App\Domain\Product\UseCases\Dtos\UpdateProductVariantDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'IDX_PRODUCTS_PARENT', columns: ['parent_id'])]
#[ORM\Index(name: 'IDX_PRODUCTS_ENABLED_SORTING', columns: ['enabled', 'sorting', 'id'])]
class Product {
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $parent;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['sorting' => 'ASC', 'id' => 'ASC'])]
    private Collection $variants;

    #[ORM\Column(length: 510)]
    private string $name;

    #[ORM\Column(name: 'name_uk', length: 510, nullable: true)]
    private ?string $nameUk = null;

    #[ORM\Column(length: 255)]
    private string $unit;

    #[ORM\Column(name: 'storage_office', type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    private string $storageOffice = '0.000';

    #[ORM\Column(name: 'storage_workshop', type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    private string $storageWorkshop = '0.000';

    #[ORM\Column(name: 'storage_third', type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    private string $storageThird = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    private string $price;

    #[ORM\Column(options: ['default' => 0])]
    private int $sorting = 0;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $type = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pallets = null;

    #[ORM\Column(name: 'inventory_name', length: 255, nullable: true)]
    private ?string $inventoryName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $forms = null;

    #[ORM\Column(name: 'weight_per_unit', type: 'decimal', precision: 12, scale: 3, nullable: true)]
    private ?string $weightPerUnit = null;

    #[ORM\Column(name: 'min_quantity', type: 'decimal', precision: 12, scale: 3, nullable: true)]
    private ?string $minQuantity = null;

    #[ORM\Column(options: ['default' => 1])]
    private bool $enabled = true;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'edit_version', options: ['default' => 0])]
    private int $editVersion = 0;

    public function __construct(int $id, string $name, string $unit, Decimal $price, \DateTimeImmutable $now, ?Product $parent = null) {
        if ($price->isNegative()) {
            throw new \DomainException('Цена товара не может быть отрицательной.');
        }
        $this->id = $id;
        $this->name = $name;
        $this->unit = $unit;
        $this->price = $price->toScale(4)->format();
        $this->importedAt = $this->updatedAt = $now;
        $this->parent = $parent;
        $this->variants = new ArrayCollection();
        $parent?->variants->add($this);
    }

    public function getId(): int {
        return $this->id;
    }

    public function stock(int $storage): Decimal {
        return Decimal::fromString(match ($storage) { 1 => $this->storageOffice, 2 => $this->storageWorkshop, default => throw new \DomainException('Некорректный склад.') }, 3);
    }

    public function changeStock(int $storage, Decimal $quantity, \DateTimeImmutable $now): void {
        if ($this->parent === null) throw new \DomainException('Остаток изменяется у цвета, а не группы.');
        $value = $this->stock($storage)->add($quantity)->toScale(3);
        if (abs($value->scaledValue()) > 999999999999) throw new \DomainException('Остаток выходит за допустимый диапазон.');
        if ($storage === 1) $this->storageOffice = $value->format();
        else $this->storageWorkshop = $value->format();
        $this->updatedAt = $now;
    }

    public function setMinimum(?Decimal $quantity, \DateTimeImmutable $now): void {
        if ($this->parent === null || $quantity?->isNegative() || ($quantity !== null && $quantity->toScale(3)->scaledValue() > 999999999999)) throw new \DomainException('Некорректный минимальный остаток.');
        $value = $quantity?->format(3);
        if ($this->minQuantity === $value) return;
        $this->minQuantity = $value;
        $this->updatedAt = $now;
        $this->editVersion++;
    }

    public function getEditVersion(): int {
        return $this->editVersion;
    }

    public function changeSorting(int $sorting, int $version, \DateTimeImmutable $now): bool {
        if ($version !== $this->editVersion) {
            throw new ProductVersionConflict();
        }
        if ($this->parent !== null || $sorting < 0 || $sorting > 2147483647) {
            throw new \DomainException('Некорректная сортировка группы.');
        }
        if ($this->sorting === $sorting) {
            return false;
        }
        $this->sorting = $sorting;
        $this->updatedAt = $now;
        $this->editVersion++;
        return true;
    }

    public function updateVariant(UpdateProductVariantDto $data, \DateTimeImmutable $now): bool {
        if ($data->version !== $this->editVersion) {
            throw new ProductVersionConflict();
        }
        if ($this->parent === null || $data->id !== $this->id) {
            throw new \DomainException('Некорректный вариант продукции.');
        }
        $name = trim($data->name);
        if ($name === '' || mb_strlen($name) > 510 || mb_strlen($data->nameUk ?? '') > 510 || $data->sorting < 0 || $data->sorting > 2147483647 || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $data->price)) {
            throw new \DomainException('Проверьте название, цену и сортировку варианта.');
        }
        $before = $this->getSettings();
        $this->name = $name;
        $this->nameUk = trim($data->nameUk ?? '') ?: null;
        $this->price = Decimal::fromString($data->price, 4)->format();
        $this->sorting = $data->sorting;
        $this->enabled = $data->enabled;
        if ($before === $this->getSettings()) {
            return false;
        }
        $this->updatedAt = $now;
        $this->editVersion++;
        return true;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getUnit(): string {
        return $this->unit;
    }

    public function getPrice(): Decimal {
        return Decimal::fromString($this->price, 4);
    }

    public function getParent(): ?Product {
        return $this->parent;
    }

    /** @return Collection<int, Product> */
    public function getVariants(): Collection {
        return $this->variants;
    }

    public function isAvailableForOrder(bool $requireActiveParent = true): bool {
        return $this->enabled && $this->parent !== null && (!$requireActiveParent || $this->parent->enabled);
    }

    /** Metadata only: balances and order snapshots never belong to this form. */
    public function getSettings(): array {
        return ['name' => $this->name, 'nameUk' => $this->nameUk, 'unit' => $this->unit,
            'price' => $this->price, 'sorting' => $this->sorting, 'type' => $this->type,
            'enabled' => $this->enabled, 'weightPerUnit' => $this->weightPerUnit,
            'minQuantity' => $this->minQuantity, 'pallets' => $this->pallets,
            'inventoryName' => $this->inventoryName, 'forms' => $this->forms];
    }

    public function updateSettings(SaveProductDto $data, \DateTimeImmutable $now): bool {
        if ($data->version !== $this->editVersion) {
            throw new ProductVersionConflict();
        }
        $before = $this->getSettings();
        $pallets = [$data->pallet0, $data->pallet1, $data->pallet2, $data->pallet3, $data->pallet4, $data->pallet5];
        foreach ($pallets as $value) {
            if ($value !== '' && !preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $value)) {
                throw new \DomainException('Параметры переложки должны быть положительными числами.');
            }
        }
        foreach ([array_slice($pallets, 0, 3), array_slice($pallets, 3, 3)] as $row) {
            if (count(array_filter($row, static fn (string $v): bool => $v !== '')) > 0) {
                foreach ($row as $value) {
                    if ($value === '' || Decimal::fromString($value, 3)->isZero() || Decimal::fromString($value, 3)->isNegative()) {
                        throw new \DomainException('Для переложки заполните все три положительных значения: ряд, рядов, поддон.');
                    }
                }
            }
        }
        $this->name = trim($data->name);
        if ($this->name === '') {
            throw new \DomainException('Укажите наименование продукции.');
        }
        $this->nameUk = trim($data->nameUk ?? '') ?: null;
        $this->unit = $this->parent?->unit ?? $data->unit;
        $this->type = $this->parent?->type ?? $data->type;
        $price = Decimal::fromString($data->price, 4);
        if ($price->isNegative()) {
            throw new \DomainException('Цена товара не может быть отрицательной.');
        }
        $this->price = $this->parent === null ? '0.0000' : $price->format();
        $this->sorting = $data->sorting;
        $this->enabled = $data->enabled;
        $this->weightPerUnit = $data->weightPerUnit === '' ? null : Decimal::fromString($data->weightPerUnit, 3)->format();
        $this->minQuantity = $data->minQuantity === '' ? null : Decimal::fromString($data->minQuantity, 3)->format();
        $this->inventoryName = trim($data->inventoryName) ?: null;
        $this->forms = trim($data->forms) ?: null;
        $this->pallets = implode(';', $pallets) === ';;;;;' ? null : rtrim(implode(';', $pallets), ';');
        if ($this->getSettings() === $before) {
            return false;
        }
        $this->updatedAt = $now;
        $this->editVersion++;

        return true;
    }

    public function inheritGroupSettings(\DateTimeImmutable $now): void {
        if ($this->parent !== null && ($this->unit !== $this->parent->unit || $this->type !== $this->parent->type)) {
            $this->unit = $this->parent->unit;
            $this->type = $this->parent->type;
            $this->updatedAt = $now;
            $this->editVersion++;
        }
    }
}
