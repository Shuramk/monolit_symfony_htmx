<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Enterprise\ReadModel\EnterpriseView;
use App\Domain\Order\ReadModel\Dtos\OrderPrintViewDto;
use App\Domain\Order\Repository\OrderViewRepository;

final readonly class OrderPrintView {
    public function __construct(
        private OrderViewRepository $orders,
        private EnterpriseView $enterprise,
        private OrderFinancialCalculator $calculator,
        private LegacyProductText $productText,
    ) {
    }

    public function find(int $id): ?OrderPrintViewDto {
        $order = $this->orders->findForPrint($id);
        if ($order === null) {
            return null;
        }

        $markup = (int) $order['markup'];
        $financial = $this->calculator->calculate($order['lines'], $order['shipping_cost'], $markup, $order['prepayment'], $order['shipment_payment']);
        $lines = [];
        foreach ($order['lines'] as $index => $line) {
            $lines[] = [
                'name' => $this->productText->format(trim($line['product_group_name'].' '.$line['product_name'])),
                'unit' => $this->productText->format($line['unit']),
                'quantity' => Decimal::fromString($line['Quantity'], 3)->format(2),
                'unitPrice' => Decimal::fromScaled($financial->lines[$index]['unitPrice'], 4)->format(2),
                'total' => Decimal::fromScaled($financial->lines[$index]['total'], 2)->format(),
            ];
        }

        $shipping = Decimal::fromString($order['shipping_cost'], 2);
        if ($markup > 0 && $markup < 20) {
            $shipping = $shipping->toScale(4)->multiplyByInt(100 + $markup)->divideByInt(100)->round(2);
        }
        if (!$shipping->isZero()) {
            // The new order stores an aggregate delivery cost, not a carrier/trip count.
            $lines[] = ['name' => 'Доставка', 'unit' => 'усл.', 'quantity' => '1.00', 'unitPrice' => $shipping->format(), 'total' => $shipping->format()];
        }
        $subtotal = Decimal::fromScaled($financial->goodsTotal, 2)->add($shipping);
        $total = Decimal::fromScaled($financial->total, 2);
        $paid = Decimal::fromScaled($financial->paidAmount, 2);

        return new OrderPrintViewDto(
            id: (int) $order['id'],
            createdDate: $this->date($order['created_at']),
            desiredDate: $this->date($order['desired_at']),
            customerName: $order['customer_name'],
            customerPhone: $order['customer_phone'] ?? '',
            shippingAddress: $order['shipping_address'] ?? '',
            managerName: $order['manager_name'],
            enterprise: $this->enterprise->getById(1),
            lines: $lines,
            subtotal: $subtotal->format(),
            vat: $markup === 20 ? $total->subtract($subtotal)->format() : null,
            total: $total->format(),
            paid: $paid->format(),
            remaining: $total->subtract($paid)->format(),
        );
    }

    private function date(string $value): string {
        $date = new \DateTimeImmutable($value);
        $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

        return sprintf("'%s' %s %s г.", $date->format('d'), $months[(int) $date->format('n') - 1], $date->format('Y'));
    }
}
