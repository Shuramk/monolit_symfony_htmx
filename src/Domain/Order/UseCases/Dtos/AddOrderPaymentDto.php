<?php declare(strict_types=1);

namespace App\Domain\Order\UseCases\Dtos;

use App\Application\Middleware\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddOrderPaymentDto implements RequestDtoInterface {
    public function __construct(
        #[Assert\Regex(pattern: '/^\d{1,10}(?:\.\d{1,2})?$/', message: 'Введите сумму: до 10 целых и 2 дробных знаков.')]
        #[Assert\GreaterThan(value: 0, message: 'Сумма оплаты должна быть больше нуля.')]
        public string $amount,
        #[Assert\Uuid(message: 'Некорректный идентификатор операции.')]
        public string $operationId,
        public bool $toCard = false,
    ) {}
}
