<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ReadModel\ShipmentQuantityParser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: "legacy:import-orders", description: "Rebuilds application orders from the read-only legacy database.")]
final class ImportLegacyOrdersCommand extends Command
{
    public function __construct(
        #[Autowire("@doctrine.dbal.legacy_connection")]
        private readonly Connection $legacy,
        #[Autowire("@doctrine.dbal.app_connection")]
        private readonly Connection $app,
        private readonly ShipmentQuantityParser $shipmentQuantityParser,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->app->fetchOne('SELECT 1 FROM order_changelog LIMIT 1') !== false) {
            (new SymfonyStyle($input, $output))->error('Повторный импорт остановлен: в новой базе есть события заказов. Полная пересборка удалит созданные или изменённые в Symfony заказы и их журнал.');

            return Command::FAILURE;
        }

        $orders = $this->legacy->fetchAllAssociative("SELECT o.orderid, o.customerID, o.userID, o.order_time, o.desired_date, o.status_id, o.shipping_address, o.comments, o.prepayment, o.shippingCost, o.markup, o.loyalty, o.loyalty_comment, c.customerName, c.customerPhone FROM sklad_orders o LEFT JOIN sklad_customers c ON c.customerID = o.customerID ORDER BY o.orderid");
        $items = $this->legacy->fetchAllAssociative("SELECT c.orderCartID, c.orderID, c.productID, c.Quantity, c.newPrice, p.name AS product_name, parent.name AS product_group_name, p.measure, p.price AS current_price FROM sklad_ordered_carts c INNER JOIN sklad_orders o ON o.orderid = c.orderID LEFT JOIN sklad_products p ON p.id = c.productID LEFT JOIN sklad_products parent ON parent.id = p.parent ORDER BY c.orderID, c.orderCartID");
        $shipments = $this->legacy->fetchAllAssociative("SELECT s.ID, s.orderID, s.taskID, s.carrierID, s.list FROM sklad_order_partial_shipment s INNER JOIN sklad_orders o ON o.orderid = s.orderID ORDER BY s.ID");
        $payments = $this->legacy->fetchAllAssociative("SELECT \"order\" AS source, p.paymentID AS source_id, p.orderID, p.paymentDate, p.sumOfPayment, p.paymentComment, p.userID, p.moneyTaken, p.toCard, p.pCashless, NULL AS shipment_id FROM sklad_payments p INNER JOIN sklad_orders o ON o.orderid = p.orderID UNION ALL SELECT \"shipment\" AS source, p.paymentID AS source_id, p.orderID, p.paymentDate, p.sumOfPayment, p.paymentComment, p.userID, p.moneyTaken, p.toCard, p.pCashless, NULL AS shipment_id FROM sklad_payments_shipment p INNER JOIN sklad_orders o ON o.orderid = p.orderID ORDER BY source, source_id");
        $history = $this->legacy->fetchAllAssociative("SELECT h.oscID, h.orderID, h.statusID, h.status_change_time, h.changelog, h.userID, h.taskID FROM sklad_order_status_changelog h INNER JOIN sklad_orders o ON o.orderid = h.orderID ORDER BY h.oscID");
        $productIds = array_fill_keys(array_map("intval", $this->app->fetchFirstColumn("SELECT id FROM products")), true);
        $customerIds = array_fill_keys(array_map("intval", $this->app->fetchFirstColumn("SELECT id FROM customers")), true);
        $now = (new \DateTimeImmutable())->format("Y-m-d H:i:s");

        $this->app->transactional(function () use ($orders, $items, $shipments, $payments, $history, $productIds, $customerIds, $now): void {
            $this->app->executeStatement("DELETE FROM order_shipment_items");
            $this->app->executeStatement("DELETE FROM order_payments");
            $this->app->executeStatement("DELETE FROM order_status_history");
            $this->app->executeStatement("DELETE FROM order_shipments");
            $this->app->executeStatement("DELETE FROM order_items");
            $this->app->executeStatement("DELETE FROM orders");

            foreach ($orders as $order) {
                $this->app->insert("orders", [
                    "id" => (int) $order["orderid"],
                    "manager_id" => (int) ($order['userID'] ?? 0) > 0 ? (int) $order['userID'] : null,
                    "customer_id" => isset($customerIds[(int) $order["customerID"]]) ? (int) $order["customerID"] : null,
                    "customer_name" => trim((string) ($order["customerName"] ?? "")),
                    "customer_phone" => $order["customerPhone"] ?: null,
                    "shipping_address" => $order["shipping_address"] ?: null,
                    "comments" => $order["comments"] ?: null,
                    "desired_at" => $order["desired_date"],
                    "created_at" => $order["order_time"],
                    "status" => $this->status((int) $order["status_id"])->value,
                    "prepayment" => $order["prepayment"] ?? "0",
                    "shipping_cost" => $order["shippingCost"] ?? "0",
                    "markup" => (int) ($order["markup"] ?? 0),
                    "loyalty" => $order["loyalty"] === null ? null : (int) $order["loyalty"],
                    "loyalty_comment" => $order["loyalty_comment"] ?: null,
                    "imported_at" => $now,
                    "updated_at" => $now,
                ]);
            }

            foreach ($items as $item) {
                $price = (string) $item["newPrice"];
                if ((float) $price < 0) {
                    $price = (string) ($item["current_price"] ?? "0");
                }

                $this->app->insert("order_items", [
                    "order_id" => (int) $item["orderID"],
                    "product_id" => $item["productID"] !== null && isset($productIds[(int) $item["productID"]]) ? (int) $item["productID"] : null,
                    "product_name" => trim((string) ($item["product_name"] ?? "")),
                    "product_group_name" => trim((string) ($item["product_group_name"] ?? "")) ?: null,
                    "unit" => trim((string) ($item["measure"] ?? "")),
                    "quantity" => $item["Quantity"],
                    "unit_price" => $price,
                    "position" => (int) $item["orderCartID"],
                ]);
            }

            foreach ($shipments as $shipment) {
                $shipmentId = (int) $shipment["ID"];
                $this->app->insert("order_shipments", [
                    "id" => $shipmentId,
                    "order_id" => (int) $shipment["orderID"],
                    "task_id" => $shipment["taskID"] === null ? null : (int) $shipment["taskID"],
                    "carrier_id" => $shipment["carrierID"] === null ? null : (int) $shipment["carrierID"],
                    "imported_at" => $now,
                ]);

                foreach ($this->shipmentQuantityParser->parse((string) $shipment["list"]) as $productId => $quantity) {
                    $this->app->insert("order_shipment_items", ["shipment_id" => $shipmentId, "product_id" => $productId, "quantity" => number_format($quantity / 1000, 3, ".", "")]);
                }
            }

            foreach ($payments as $payment) {
                if ($payment["orderID"] === null) {
                    continue;
                }

                $this->app->insert("order_payments", [
                    "source" => $payment["source"],
                    "source_id" => (int) $payment["source_id"],
                    "order_id" => (int) $payment["orderID"],
                    "paid_at" => $payment["paymentDate"],
                    "amount" => $payment["sumOfPayment"],
                    "comment" => $payment["paymentComment"] ?: null,
                    "user_id" => $payment["userID"] === null ? null : (int) $payment["userID"],
                    "shipment_id" => null,
                    "money_taken" => $payment['moneyTaken'],
                    "to_card" => $payment['toCard'],
                    "payment_markup" => $payment['pCashless'],
                    "metadata_imported_at" => $now,
                    "imported_at" => $now,
                ]);
            }

            foreach ($history as $event) {
                $this->app->insert("order_status_history", [
                    "id" => (int) $event["oscID"],
                    "order_id" => (int) $event["orderID"],
                    "status" => $this->status((int) $event["statusID"])->value,
                    "changed_at" => $event["status_change_time"],
                    "comment" => (string) ($event["changelog"] ?? ""),
                    "user_id" => $event["userID"] === null ? null : (int) $event["userID"],
                    "task_id" => $event["taskID"] === null ? null : (int) $event["taskID"],
                    "imported_at" => $now,
                ]);
            }
        });

        (new SymfonyStyle($input, $output))->success(sprintf("Imported %d orders, %d items, %d shipments, %d payments and %d status events.", count($orders), count($items), count($shipments), count($payments), count($history)));

        return Command::SUCCESS;
    }

    private function status(int $legacyStatusId): OrderStatus
    {
        return match ($legacyStatusId) {
            1 => OrderStatus::New,
            2 => OrderStatus::InProgress,
            3 => OrderStatus::ReadyForShipment,
            4 => OrderStatus::Delivered,
            5 => OrderStatus::PartiallyShipped,
            6 => OrderStatus::PaidAndDelivered,
            7 => OrderStatus::Cancelled,
            default => OrderStatus::New,
        };
    }
}
