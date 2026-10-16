<?php declare(strict_types=1);

namespace App\Domain\Order\Repository;

use Doctrine\DBAL\Connection;

final readonly class OrderChangelogRepository {
    public function __construct(private Connection $connection) {}

    /** @return array<string, mixed> */
    public function snapshot(int $orderId): array {
        $order = $this->connection->fetchAssociative(
            'SELECT id, customer_id, customer_name, customer_phone, shipping_address, shipping_cost, carrier_id, shipping_trips, shipping_unit_price, desired_at, comments, markup, status FROM orders WHERE id = :id',
            ['id' => $orderId],
        );
        if ($order === false) {
            throw new \DomainException('Заказ для записи в журнал не найден.');
        }

        $order['lines'] = $this->connection->fetchAllAssociative(
            'SELECT id, product_id, product_name, product_group_name, unit, quantity, unit_price, position FROM order_items WHERE order_id = :id ORDER BY position, id',
            ['id' => $orderId],
        );

        return $order;
    }

    /** @param array<string, mixed> $snapshot */
    public function append(int $orderId, string $event, string $message, array $snapshot, int $userId, \DateTimeImmutable $now): void {
        $this->connection->insert('order_changelog', [
            'order_id' => $orderId,
            'event' => $event,
            'message' => $message,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'user_id' => $userId,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }
}
