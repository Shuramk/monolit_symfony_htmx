<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Import;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LegacyWarehouseImporter {
    public function __construct(
        #[Autowire('@doctrine.dbal.legacy_connection')] private Connection $legacy,
        #[Autowire('@doctrine.dbal.app_connection')] private Connection $app,
    ) {}

    public function import(): array {
        return $this->app->transactional(function (): array {
            $batch = $this->app->fetchAssociative("SELECT * FROM warehouse_imports WHERE id = 'legacy-v1' FOR UPDATE");
            if (!$batch) throw new \LogicException('Сначала выполните миграции склада.');
            if ($batch['completed_at'] !== null) return ['alreadyImported' => true, 'counts' => json_decode($batch['counts'], true, 512, JSON_THROW_ON_ERROR)];
            $maps = [
                'sklad_history' => ['warehouse_history', fn ($r) => ['id'=>$r['id'],'product_id'=>$r['prod_id'],'user_id'=>$r['userID'],'shift_id'=>$r['user_id'],'created_at'=>self::date($r['date']),'action'=>$r['action'],'quantity'=>$r['num_stor'],'storage'=>$r['storage'] ?: 2,'comment'=>$r['comment'],'manufacturing_date'=>self::date($r['manufacturingDate']),'task_id'=>$r['taskID'],'operation_key'=>'legacy:'.$r['id']]],
                'sklad_tasks' => ['warehouse_shipping_tasks', fn ($r) => ['id'=>$r['id'],'order_id'=>$r['orderID'] ?: null,'status'=>$r['status'],'type'=>$r['type'],'performance_date'=>self::date($r['dateOfPerformance']),'completed_at'=>self::date($r['dateOfCompletion']),'created_at'=>self::date($r['date']),'comment'=>$r['comment']]],
                'sklad_tasks_carts' => ['warehouse_shipping_items', fn ($r) => ['id'=>$r['tcID'],'task_id'=>$r['taskID'],'product_id'=>$r['productID'],'quantity'=>$r['Quantity'],'price'=>$r['Price'] ?? '0']],
                'sklad_tasks_production' => ['production_tasks', fn ($r) => ['id'=>$r['tpID'],'product_id'=>(int)$r['productID'],'quantity'=>$r['Quantity'],'press_id'=>$r['pressID'],'sorting'=>$r['order_sort'] ?? 0,'done'=>(int)($r['tpDone'] !== null),'created_at'=>self::date($r['tpDate']),'due_at'=>self::date($r['dateCompletion']),'user_id'=>$r['userID'],'comment'=>$r['taskComment'],'operation_key'=>'legacy:'.$r['tpID']]],
                'sklad_task_p_drafts' => ['production_entries', fn ($r) => ['id'=>$r['id'],'task_id'=>$r['tpID'],'product_id'=>$r['productID'],'quantity'=>$r['Quantity'],'press_id'=>$r['pressID'],'shift_id'=>$r['shiftID'],'created_at'=>self::date($r['date']),'user_id'=>$r['userID'],'accepted_at'=>self::date($r['ssDate']),'accepted_by'=>$r['ssUserID'],'comment'=>$r['comment'],'operation_key'=>'legacy:'.$r['id']]],
                'sklad_materials' => ['raw_materials', fn ($r) => ['id'=>$r['materialID'],'name'=>$r['name'],'unit'=>$r['measureID'],'ratio'=>$r['mRatioKG'],'production'=>(int)($r['production'] == 1)]],
                'sklad_materials_history' => ['raw_material_history', fn ($r) => ['id'=>$r['historyID'],'material_id'=>$r['materialID'],'quantity'=>$r['mQuantity'],'created_at'=>self::date($r['date']),'user_id'=>$r['userID'],'price'=>$r['mPrice'],'comment'=>$r['comment']]],
                'sklad_material_balances' => ['raw_material_balances', fn ($r) => ['id'=>$r['id'],'material_id'=>$r['materialID'],'balance'=>$r['balance'],'created_at'=>self::date($r['date'])]],
                'sklad_production_recipes' => ['production_recipes', fn ($r) => ['id'=>$r['prID'],'product_id'=>$r['productID'],'press_id'=>$r['pressID'],'output_quantity'=>$r['prOutput'],'components'=>self::json(self::serialized($r['pRecipe'])),'created_at'=>self::date($r['date'])]],
            ];
            $counts = [];
            foreach ($maps as $source => [$target, $map]) {
                if ((int)$this->app->fetchOne("SELECT COUNT(*) FROM $target") > 0) throw new \DomainException("Импорт остановлен: $target уже содержит данные. Они не будут перезаписаны.");
                $buffer = []; $count = 0;
                foreach ($this->legacy->iterateAssociative("SELECT * FROM $source") as $row) {
                    $buffer[] = $map($row) + ['payload'=>self::json($row)];
                    $count++;
                    if (count($buffer) === 200) { $this->insertBatch($target, $buffer); $buffer = []; }
                }
                if ($buffer !== []) $this->insertBatch($target, $buffer);
                $counts[$target] = $count;
            }
            $days = [];
            foreach ($this->legacy->iterateAssociative('SELECT * FROM sklad_timeTable') as $row) $days[$row['ttID']] = ['id'=>$row['ttID'],'attendance'=>self::json(self::serialized($row['dayTTable'])),'forms'=>null,'payload'=>self::json(['timetable'=>$row])];
            foreach ($this->legacy->iterateAssociative('SELECT * FROM sklad_tt_presses') as $row) {
                $id = $row['ttID'];
                $day = $days[$id] ?? ['id'=>$id,'attendance'=>null,'forms'=>null,'payload'=>'{}'];
                $day['forms'] = self::json(self::serialized($row['pressForms']));
                $day['payload'] = self::json(json_decode($day['payload'], true, 512, JSON_THROW_ON_ERROR) + ['presses'=>$row]);
                $days[$id] = $day;
            }
            foreach (array_chunk(array_values($days), 200) as $chunk) $this->insertBatch('warehouse_shift_days', $chunk);
            $counts['warehouse_shift_days'] = count($days);
            $this->app->update('warehouse_imports', ['completed_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'counts'=>self::json($counts)], ['id'=>'legacy-v1']);
            return ['alreadyImported'=>false,'counts'=>$counts];
        });
    }

    private function insertBatch(string $table, array $rows): void {
        $columns = array_keys($rows[0]);
        $placeholders = '('.implode(',', array_fill(0, count($columns), '?')).')';
        $parameters = [];
        foreach ($rows as $row) array_push($parameters, ...array_values($row));
        $this->app->executeStatement('INSERT INTO '.$table.' ('.implode(',', $columns).') VALUES '.implode(',', array_fill(0, count($rows), $placeholders)), $parameters);
    }

    public static function date(?string $date): ?string {
        return !$date || str_starts_with($date, '0000-') ? null : $date;
    }

    public static function serialized(?string $value): array {
        if (!$value || $value === 'N;') return [];
        $data = @unserialize($value, ['allowed_classes'=>false]);
        if (!is_array($data)) throw new \DomainException('Некорректный сериализованный массив в legacy. Импорт отменён.');
        array_walk_recursive($data, static function ($item): void { if (is_object($item)) throw new \DomainException('Объекты в сериализованных данных legacy запрещены.'); });
        return $data;
    }

    private static function json(array $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
