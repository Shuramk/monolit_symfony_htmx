<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases;

use App\Application\Controller\Order\Dtos\OrderSelectCustomerDto;
use App\Domain\Customer\Repository\CustomerRepository;
use App\Domain\Order\Services\OrderDraftSession;

final readonly class SelectOrderCustomer {
    public function __construct(private CustomerRepository $customers, private OrderDraftSession $draft) {}

    public function execute(OrderSelectCustomerDto $dto): void {
        $customer = $this->customers->getById($dto->customerId);
        $this->draft->selectCustomer($customer->getId());
    }
}
