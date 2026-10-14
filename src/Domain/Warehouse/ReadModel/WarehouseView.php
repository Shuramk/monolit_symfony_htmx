<?php declare(strict_types=1);

namespace App\Domain\Warehouse\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Warehouse\ReadModel\Dtos\WarehousePageDto;
use App\Domain\Warehouse\ReadModel\Dtos\WarehouseOverviewDto;
use App\Domain\Warehouse\ReadModel\Dtos\WarehouseInventoryDto;
use App\Domain\Warehouse\ReadModel\Dtos\WarehouseDetailsDto;
use App\Domain\Warehouse\Repository\WarehouseViewRepository;

final readonly class WarehouseView {
    public function __construct(private WarehouseViewRepository $repository) {}

    public function overview(): WarehouseOverviewDto {
        $sections = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($this->repository->overview() as $group) {
            $type = (int) $group['type'];
            if (!isset($sections[$type])) continue;
            $office = Decimal::fromString((string) $group['office'], 3);
            $workshop = Decimal::fromString((string) $group['workshop'], 3);
            $group['office'] = $this->compact($office);
            $group['workshop'] = $this->compact($workshop);
            $group['total'] = $this->compact($office->add($workshop));
            $group['unit'] = str_replace(['<sup>2</sup>', '<sup>3</sup>'], ['²', '³'], $group['unit']);
            $sections[$type][] = $group;
        }
        return new WarehouseOverviewDto($sections);
    }

    public function findGroup(int $id): ?array {
        return $this->repository->findGroup($id);
    }

    public function details(int $id): ?WarehouseDetailsDto {
        $this->repository->requireImported();
        $group = $this->findGroup($id);
        if ($group === null) return null;
        $rows = $this->repository->rows('', $id, 2000, 0);
        foreach ($rows as &$row) {
            $total = Decimal::fromString($row['storage_office'],3)->add(Decimal::fromString($row['storage_workshop'],3))->add(Decimal::fromString($row['storage_third'],3));
            $tasks = Decimal::fromString((string)$row['task_quantity'],3);
            $cover = Decimal::fromString((string)$row['cover_quantity'],3);
            $minimum = Decimal::fromString($row['min_quantity'] ?? '0',3)->subtract(Decimal::fromString($row['storage_workshop'],3))->add($tasks)->subtract($cover);
            $row['stock'] = $this->compact($total);
            $row['unallocated'] = $total->subtract($tasks)->format(2);
            $row['storage_office'] = Decimal::fromString($row['storage_office'],3)->format(2);
            $row['storage_workshop'] = Decimal::fromString($row['storage_workshop'],3)->format(2);
            $row['price'] = Decimal::fromString($row['price'],4)->format(2);
            $row['task_quantity'] = $tasks->format(2);
            $row['reserved'] = Decimal::fromString((string)$row['reserved'],3)->format(2);
            $row['cover_quantity'] = $this->compact($cover);
            $row['needed'] = $minimum->isNegative() ? '0.000' : $minimum->format(3);
            $row['unit'] = $this->unit($row['unit']);
        }
        $tasks = []; $orders = [];
        unset($row);
        foreach ($this->repository->shippingDetails($id) as $row) $tasks[$row['product_id']][] = $row;
        foreach ($this->repository->orderDetails($id) as $row) $orders[$row['product_id']][] = $row;
        return new WarehouseDetailsDto($group, $rows, $tasks, $orders);
    }

    public function history(int $id, int $page): ?array {
        $this->repository->requireImported();
        $product = $this->repository->product($id);
        if (!$product) return null;
        $product['unit'] = $this->unit($product['unit']);
        return ['product'=>$product] + $this->repository->history($id,$page);
    }

    public function needed(int $id): ?array {
        $details = $this->details($id);
        if (!$details) return null;
        return array_map(static fn ($row): array => ['productId'=>(int)$row['id'],'quantity'=>$row['needed'],'unit'=>$row['unit']], $details->products);
    }

    public function production(int $groupId): ?array {
        $this->repository->requireImported();
        $group = $this->findGroup($groupId);
        if (!$group) return null;
        $tasks = $this->repository->production($groupId);
        foreach ($tasks as &$task) {
            $task['remaining'] = Decimal::fromString($task['quantity'],3)->subtract(Decimal::fromString((string)$task['completed'],3))->format(3);
            $task['unit'] = $this->unit($task['unit']);
        }
        $cover = $this->repository->cover($groupId);
        foreach ($cover as &$entry) $entry['unit'] = $this->unit($entry['unit']);
        return ['group'=>$group,'tasks'=>$tasks,'cover'=>$cover];
    }

    private function unit(string $unit): string {
        return str_replace(['<sup>2</sup>','<sup>3</sup>'], ['²','³'], $unit);
    }

    public function inventory(int $type): WarehouseInventoryDto {
        if (!in_array($type, [1, 2], true)) throw new \DomainException('Некорректный тип продукции.');
        $groups = $this->overview()->sections[$type];
        $variants = [];
        foreach ($this->repository->inventoryVariants($type) as $variant) {
            $variants[(int) $variant['parent_id']][] = $variant;
        }
        foreach ($groups as &$group) {
            $group['variants'] = $variants[(int) $group['id']] ?? [];
            $values = explode(';', $group['pallets'] ?? '');
            $group['palletRows'] = [];
            foreach ([0, 3] as $offset) {
                if (!isset($values[$offset+2]) || $values[$offset] === '') continue;
                $perRow = $values[$offset+2];
                $normalized = rtrim(rtrim($perRow, '0'), '.');
                $label = match ($normalized) { '0.96', '0.91' => 'стандарт', '1.2' => 'не стандарт', default => 'переложка' };
                $group['palletRows'][] = ['label' => $label, 'total' => $values[$offset], 'rows' => $values[$offset+1], 'perRow' => $perRow];
            }
        }
        return new WarehouseInventoryDto($type, $groups, (new \DateTimeImmutable())->format('d-m-Y'));
    }

    private function compact(Decimal $quantity): string {
        $value = rtrim(rtrim($quantity->format(), '0'), '.');
        return $value === '-0' ? '0' : $value;
    }

    public function page(string $query, int $groupId, int $page): WarehousePageDto {
        $total = $this->repository->count($query, $groupId);
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($pages, $page));
        $rows = $this->repository->rows($query, $groupId, 50, ($page - 1) * 50);
        foreach ($rows as &$row) {
            $reserved = Decimal::fromString((string) $row['reserved'], 3);
            $totalStock = Decimal::fromString($row['storage_office'], 3)->add(Decimal::fromString($row['storage_workshop'], 3));
            $row['reserved'] = $reserved->format();
            $row['total'] = $totalStock->format();
            $row['free'] = $totalStock->subtract($reserved)->format();
            $row['unit'] = str_replace(['<sup>2</sup>', '<sup>3</sup>'], ['²', '³'], $row['unit']);
        }
        return new WarehousePageDto($rows, $this->repository->groups(), $page, $pages, $total, $query, $groupId);
    }
}
