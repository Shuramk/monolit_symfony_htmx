<?php declare(strict_types=1);

namespace App\Domain\Crm\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Crm\Repository\CrmBoardRepository;

final readonly class CrmBoardView {
    public function __construct(private CrmBoardRepository $repository) {}

    public function get(string $state, string $query, array $managers, \DateTimeImmutable $now): array {
        $deals = $this->repository->deals($state, $query, $managers);
        $orders = $this->repository->orders($query, $managers);
        $shipments = $this->repository->shipments($query, $managers);
        $orderIds = array_values(array_unique(array_filter(array_merge(array_column($orders,'id'), array_column($shipments,'order_id')))));

        $lines = [];
        $prices = [];
        foreach ($this->repository->orderLines($orderIds) as $line) {
            $lines[$line['order_id']][] = $line;
            $prices[$line['order_id']][$line['product_id']] = $line['unit_price'];
        }

        $shipmentLines = [];
        foreach ($this->repository->shipmentLines(array_column($shipments,'id')) as $line) {
            $shipmentLines[$line['task_id']][] = $line;
        }

        $dealTotal = Decimal::zero(2);
        $orderTotal = Decimal::zero(2);
        $unpaidTotal = Decimal::zero(2);
        $taskTotal = Decimal::zero(2);
        $approvedTotal = Decimal::zero(2);
        foreach ($deals as &$deal) {
            $deal['deadline'] = $this->deadline($deal['due_at'], $now);
            $dealTotal = $dealTotal->add(Decimal::fromString((string)$deal['budget'], 2));
        }
        unset($deal);

        foreach ($orders as &$order) {
            $amount = $this->amount($lines[$order['id']] ?? [], (int)$order['markup']);
            $unpaid = $amount->subtract(Decimal::fromString((string)$order['prepayment'], 2));
            $order['amount'] = $amount->format();
            $order['unpaid'] = $unpaid->format();
            $order['deadline'] = $this->deadline($order['desired_at'], $now);
            $orderTotal = $orderTotal->add($amount);
            $unpaidTotal = $unpaidTotal->add($unpaid);
        }
        unset($order);

        $pending = [];
        $approved = [];
        foreach ($shipments as $task) {
            $payload = json_decode($task['payload'], true, 512, JSON_THROW_ON_ERROR);
            $task['allowed'] = (int)($payload['allowed'] ?? 0) === 1;
            $task['collected'] = (int)($payload['collected'] ?? 0) === 1;
            $task['lines'] = $shipmentLines[$task['id']] ?? [];
            $pricedLines = [];
            foreach ($task['lines'] as $line) {
                $pricedLines[] = ['quantity'=>$line['quantity'], 'unit_price'=>$prices[$task['order_id']][$line['product_id']] ?? '0'];
            }
            $amount = $this->amount($pricedLines, (int)($task['markup'] ?? 0));
            $task['amount'] = $amount->format();
            $task['deadline'] = $this->deadline($task['performance_date'], $now);
            if ($task['allowed']) {
                $approved[] = $task;
                $approvedTotal = $approvedTotal->add($amount);
            } else {
                $pending[] = $task;
                $taskTotal = $taskTotal->add($amount);
            }
        }

        return [
            'deals' => $deals,
            'orders' => $orders,
            'shipments' => $pending,
            'approved' => $approved,
            'dealTotal' => $dealTotal->format(),
            'orderTotal' => $orderTotal->format(),
            'unpaidTotal' => $unpaidTotal->format(),
            'taskTotal' => $taskTotal->format(),
            'approvedTotal' => $approvedTotal->format(),
        ];
    }

    private function amount(array $lines, int $markup): Decimal {
        $amount = Decimal::zero(7);
        foreach ($lines as $line) {
            $amount = $amount->add(Decimal::fromString((string)$line['quantity'],3)->multiply(Decimal::fromString((string)$line['unit_price'],4)));
        }
        if ($markup > 0 && $markup <= 20) {
            $amount = $amount->multiplyByInt(100 + $markup)->divideByInt(100);
        }

        return $amount->round(2);
    }

    private function deadline(?string $value, \DateTimeImmutable $now): array {
        if (!$value) {
            return ['label'=>'', 'class'=>''];
        }

        $due = new \DateTimeImmutable($value, new \DateTimeZone('Europe/Kyiv'));
        $seconds = $due->getTimestamp() - $now->getTimestamp();
        if ($seconds < 0) {
            $minutes = max(1, (int)round(-$seconds / 60));
            $hours = (int)round(-$seconds / 3600);
            $label = $minutes < 60 ? '-'.$minutes.' мин.' : ($hours < 24 ? '-'.$hours.' ч.' : '-'.(int)round(-$seconds / 86400).' дн.');

            return ['label'=>$label, 'class'=>'overdue'];
        }

        return ['label'=>$due->format('d-m-Y H:i'), 'class'=>$seconds < 86400 ? 'today' : ($seconds < 172800 ? 'soon' : 'future')];
    }
}
