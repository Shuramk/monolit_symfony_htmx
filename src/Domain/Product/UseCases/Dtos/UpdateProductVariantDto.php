<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases\Dtos;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductVariantDto {
    public function __construct(
        #[Assert\Positive] public int $id,
        #[Assert\PositiveOrZero] public int $version,
        #[Assert\NotBlank(normalizer: 'trim')] #[Assert\Length(max: 510)] public string $name,
        #[Assert\Regex('/^\d{1,8}(?:\.\d{1,4})?$/')] public string $price,
        #[Assert\Range(min: 0, max: 2147483647)] public int $sorting,
        public bool $enabled,
        #[Assert\Length(max: 510)] public ?string $nameUk = null,
    ) {}
}
