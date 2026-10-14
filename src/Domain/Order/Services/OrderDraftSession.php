<?php declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Application\Controller\Order\Dtos\OrderDraftDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class OrderDraftSession {
    private const KEY = "order_draft";

    public function __construct(private RequestStack $requestStack) {
    }

    public function save(OrderDraftDto $dto): void {
        $this->requestStack->getSession()->set(self::KEY, [
            "lines" => array_map(static fn (CreateOrderLineDto $line): array => get_object_vars($line), array_values($dto->lines)),
            "markup" => $dto->markup,
        ]);
    }

    public function load(): array {
        return $this->requestStack->getSession()->get(self::KEY, []);
    }

    public function hasLines(): bool {
        return ($this->load()["lines"] ?? []) !== [];
    }

    public function clear(): void {
        $this->requestStack->getSession()->remove(self::KEY);
    }
}
