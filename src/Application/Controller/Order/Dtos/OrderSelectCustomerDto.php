<?php declare(strict_types=1);

namespace App\Application\Controller\Order\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderSelectCustomerDto implements RequestDtoInterface {
    public function __construct(#[Assert\Positive] public int $customerId) {}
}
