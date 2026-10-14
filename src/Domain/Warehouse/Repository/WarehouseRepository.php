<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Repository;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Product\Entities\Product;
use App\Domain\Warehouse\Entities\ProductionRecipe;
use App\Domain\Warehouse\Entities\ProductionTask;
use Doctrine\DBAL\Connection;

final readonly class WarehouseRepository {
    public function __construct(private Connection $connection) {}

    public function requireImported(): void {
        if (!$this->connection->fetchOne("SELECT completed_at FROM warehouse_imports WHERE id = 'legacy-v1'")) throw new \DomainException('Сначала выполните legacy:import-warehouse.');
    }

    public function operation(string $key): ?array {
        return $this->connection->fetchAssociative('SELECT * FROM warehouse_history WHERE operation_key = ?', [$key]) ?: null;
    }

    public function setOperationWarnings(int $id, array $request, array $warnings): void {
        $this->connection->update('warehouse_history', ['payload'=>json_encode(['request'=>$request,'warnings'=>$warnings], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], ['id'=>$id]);
    }

    public function setAcceptanceWarnings(int $id, int $entryId, array $warnings): void {
        $this->connection->update('warehouse_history', ['payload'=>json_encode(['productionEntryId'=>$entryId,'warnings'=>$warnings], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], ['id'=>$id]);
    }

    public function record(Product $product, int $userId, string $action, Decimal $quantity, int $storage, string $comment, ?int $shift, ?string $manufacturingDate, string $key, array $payload, ?int $taskId = null): int {
        $this->connection->insert('warehouse_history', ['product_id'=>$product->getId(),'user_id'=>$userId,'action'=>$action,'quantity'=>$quantity->format(3),'storage'=>$storage,'comment'=>$comment,'shift_id'=>$shift,'manufacturing_date'=>$manufacturingDate,'created_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'operation_key'=>$key,'payload'=>json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),'task_id'=>$taskId]);
        return (int)$this->connection->lastInsertId();
    }

    public function consumeMaterials(Product $product, Decimal $quantity, int $shift, string $date, int $userId, int $historyId): array {
        $press = $shift === 111 ? 0 : intdiv($shift % 100, 10);
        $row = $this->connection->fetchAssociative('SELECT * FROM production_recipes WHERE product_id = ? AND press_id = ? ORDER BY id LIMIT 1', [$product->getId(), $press]);
        if (!$row) return ['Нет рецепта для '.$product->getParent()?->getName().' '.$product->getName().'. Приход сохранён без списания сырья.'];
        $materials = $this->connection->fetchAllAssociativeIndexed('SELECT id, ratio, production FROM raw_materials');
        $recipe = new ProductionRecipe((string)$row['output_quantity'], json_decode($row['components'], true, 512, JSON_THROW_ON_ERROR));
        foreach ($recipe->consumption($quantity, $materials) as $id => $amount) {
            if ($amount->isZero()) continue;
            $this->connection->insert('raw_material_history', ['material_id'=>$id,'quantity'=>Decimal::zero(3)->subtract($amount)->format(3),'created_at'=>$date,'user_id'=>$userId,'comment'=>'Производство. '.$product->getParent()?->getName().' '.$product->getName().' '.$quantity->format(3).$product->getUnit(),'warehouse_history_id'=>$historyId]);
        }
        return [];
    }

    public function shiftWarnings(int $groupId, int $shift, string $date): array {
        $id = (int)(new \DateTimeImmutable($date))->format('ymd');
        $row = $this->connection->fetchAssociative('SELECT attendance, forms FROM warehouse_shift_days WHERE id = ?', [$id]);
        $attendance = $row && $row['attendance'] ? json_decode($row['attendance'], true, 512, JSON_THROW_ON_ERROR) : [];
        $forms = $row && $row['forms'] ? json_decode($row['forms'], true, 512, JSON_THROW_ON_ERROR) : [];
        $workers = $attendance['P'][$shift] ?? [];
        $warnings = [];
        if (array_sum(array_map('intval', array_slice($workers, 0, 3))) === 0) $warnings[] = 'Смена '.$shift.' не отмечена работающей в табеле на '.$date.'.';
        $press = $shift === 111 ? 0 : intdiv($shift % 100, 10);
        if ($press > 0 && !in_array($groupId, array_map('intval', $forms[$press] ?? []), true)) $warnings[] = 'На пресс '.$press.' не указана эта форма на '.$date.'.';
        return $warnings;
    }

    public function taskForUpdate(int $id): ?ProductionTask {
        $row = $this->connection->fetchAssociative('SELECT * FROM production_tasks WHERE id = ? FOR UPDATE', [$id]);
        return $row ? new ProductionTask((int)$row['id'], (int)$row['product_id'], (int)$row['press_id'], Decimal::fromString($row['quantity'], 3), (bool)$row['done']) : null;
    }

    public function completed(int $id): Decimal {
        return Decimal::fromString((string)$this->connection->fetchOne('SELECT COALESCE(SUM(quantity),0) FROM production_entries WHERE task_id = ?', [$id]), 3);
    }

    public function entryByKey(string $key): ?array {
        return $this->connection->fetchAssociative('SELECT * FROM production_entries WHERE operation_key = ?', [$key]) ?: null;
    }

    public function entryForUpdate(int $id): ?array {
        return $this->connection->fetchAssociative('SELECT * FROM production_entries WHERE id = ? FOR UPDATE', [$id]) ?: null;
    }

    public function addEntry(ProductionTask $task, Decimal $quantity, int $shift, string $date, int $userId, string $key, bool $done): int {
        $this->connection->insert('production_entries', ['task_id'=>$task->id,'product_id'=>$task->productId,'quantity'=>$quantity->format(3),'press_id'=>$task->pressId,'shift_id'=>$shift,'created_at'=>$date.' 23:59:00','user_id'=>$userId,'operation_key'=>$key]);
        $id = (int)$this->connection->lastInsertId();
        $this->connection->update('production_tasks', ['done'=>(int)$done], ['id'=>$task->id]);
        return $id;
    }

    public function acceptEntry(int $id, int $userId): void {
        $this->connection->update('production_entries', ['accepted_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'accepted_by'=>$userId], ['id'=>$id]);
    }

    public function acceptPart(array $entry, Decimal $quantity, int $userId, bool $close = false): void {
        $original = Decimal::fromString($entry['quantity'],3);
        $remaining = $original->subtract($quantity);
        if (!$close && !$remaining->isNegative() && !$remaining->isZero()) {
            $this->connection->update('production_entries',['quantity'=>$remaining->format(3)],['id'=>$entry['id']]);
            $this->connection->insert('production_entries',['task_id'=>$entry['task_id'],'product_id'=>$entry['product_id'],'quantity'=>$quantity->format(3),'press_id'=>$entry['press_id'],'shift_id'=>$entry['shift_id'],'created_at'=>$entry['created_at'],'user_id'=>$userId,'accepted_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'accepted_by'=>$userId,'comment'=>'Внесено на склад.']);
        } else {
            $this->acceptEntry((int)$entry['id'],$userId);
            if ($close) $this->connection->insert('production_entries',['task_id'=>$entry['task_id'],'product_id'=>$entry['product_id'],'quantity'=>Decimal::zero(3)->subtract($original)->format(3),'created_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'user_id'=>$userId,'accepted_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'accepted_by'=>$userId,'comment'=>'Переложка закрыта без прихода.']);
        }
    }

    public function createTask(int $productId, Decimal $quantity, int $press, string $date, string $comment, int $userId, string $key): int {
        $existing = $this->connection->fetchAssociative('SELECT * FROM production_tasks WHERE operation_key = ?', [$key]);
        if ($existing) {
            if ((int)$existing['product_id'] !== $productId || !Decimal::fromString($existing['quantity'],3)->equals($quantity) || (int)$existing['press_id'] !== $press || substr($existing['due_at'],0,10) !== $date || $existing['comment'] !== $comment) throw new \DomainException('Ключ задания уже использован для других данных.');
            return (int)$existing['id'];
        }
        $this->connection->insert('production_tasks', ['product_id'=>$productId,'quantity'=>$quantity->format(3),'press_id'=>$press,'sorting'=>(int)$this->connection->fetchOne('SELECT COALESCE(MAX(sorting),0)+1 FROM production_tasks'),'created_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'due_at'=>$date.' 00:00:00','user_id'=>$userId,'comment'=>$comment,'operation_key'=>$key]);
        return (int)$this->connection->lastInsertId();
    }
}
