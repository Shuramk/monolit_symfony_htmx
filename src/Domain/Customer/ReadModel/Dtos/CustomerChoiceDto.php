<?php declare(strict_types=1);

namespace App\Domain\Customer\ReadModel\Dtos;

final readonly class CustomerChoiceDto {
    public function __construct(public int $id, public string $name, public ?string $phone) {}
}
