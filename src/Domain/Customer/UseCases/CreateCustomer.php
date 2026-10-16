<?php declare(strict_types=1);

namespace App\Domain\Customer\UseCases;

use App\Domain\Customer\Repository\CustomerRepository;
use App\Domain\Customer\UseCases\Dtos\CreateCustomerDto;

final readonly class CreateCustomer {
    public function __construct(private CustomerRepository $repository) {}

    public function execute(CreateCustomerDto $dto, int $actor, \DateTimeImmutable $now): int {
        $phone = preg_replace('/\D/', '', $dto->phone);
        if (strlen($phone) < 6) {
            throw new \DomainException('Укажите корректный телефон клиента.');
        }

        return $this->repository->create(
            trim($dto->name), $phone, 'customer:' . $actor . ':' . strtolower($dto->operationId),
            $now->setTimezone(new \DateTimeZone('Europe/Kyiv')),
        );
    }
}
