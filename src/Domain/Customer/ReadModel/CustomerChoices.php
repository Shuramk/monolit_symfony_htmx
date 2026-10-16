<?php declare(strict_types=1);

namespace App\Domain\Customer\ReadModel;

use App\Domain\Customer\ReadModel\Dtos\CustomerChoiceDto;
use App\Domain\Customer\ReadModel\Dtos\CustomerSearchDto;
use App\Domain\Customer\Repository\CustomerViewRepository;

final readonly class CustomerChoices {
    public function __construct(private CustomerViewRepository $repository) {}

    public function search(CustomerSearchDto $filter): array {

        return array_map($this->map(...), $this->repository->search($filter->q));
    }

    public function find(int $id): CustomerChoiceDto {
        $row = $this->repository->find($id) ?? throw new \DomainException('Клиент не найден.');

        return $this->map($row);
    }

    private function map(array $row): CustomerChoiceDto {

        return new CustomerChoiceDto((int) $row['id'], $row['name'], $row['phone']);
    }
}
