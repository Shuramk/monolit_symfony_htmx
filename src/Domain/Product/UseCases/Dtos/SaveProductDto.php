<?php declare(strict_types=1);

namespace App\Domain\Product\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveProductDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 510)]
        public string $name,
        #[Assert\Length(max: 510)]
        public ?string $nameUk = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $unit = 'шт',
        #[Assert\Regex('/^\d{1,8}(?:\.\d{1,4})?$/')]
        public string $price = '0',
        #[Assert\Choice(choices: [1, 2, 3, 4])]
        public int $type = 3,
        #[Assert\Range(min: 0, max: 2147483647)]
        public int $sorting = 100,
        public bool $enabled = true,
        #[Assert\Regex('/^(?:\d{1,9}(?:\.\d{1,3})?)?$/')]
        public string $weightPerUnit = '',
        #[Assert\Regex('/^(?:\d{1,9}(?:\.\d{1,3})?)?$/')]
        public string $minQuantity = '',
        #[Assert\Length(max: 255)]
        public string $inventoryName = '',
        #[Assert\Length(max: 255)]
        public string $forms = '',
        public string $pallet0 = '',
        public string $pallet1 = '',
        public string $pallet2 = '',
        public string $pallet3 = '',
        public string $pallet4 = '',
        public string $pallet5 = '',
        #[Assert\PositiveOrZero]
        public int $version = 0,
    ) {}
}
