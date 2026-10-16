<?php declare(strict_types=1);

namespace App\Domain\Crm\Import;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LegacyCrmImporter {
    public function __construct(
        #[Autowire('@doctrine.dbal.legacy_connection')] private Connection $legacy,
        #[Autowire('@doctrine.dbal.app_connection')] private Connection $app,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%env(default::LEGACY_CRM_FILES_DIR)%')] private ?string $legacyFilesDir = null,
    ) {}

    public function import(): array {
        return $this->app->transactional(function (): array {
            // The marker's unique key serializes concurrent imports; rollback releases it.
            if ($this->app->fetchOne("SELECT 1 FROM crm_imports WHERE name='legacy'") !== false) return ['alreadyImported'=>true];
            $filesDir = $this->legacyFilesDir ?: dirname($this->projectDir).'/monolit_old/taskfiles';
            if ($this->legacyFilesDir && (!is_dir($filesDir) || !is_readable($filesDir))) {
                throw new \DomainException('Каталог LEGACY_CRM_FILES_DIR не существует или недоступен для чтения.');
            }
            $this->app->insert('crm_imports',['name'=>'legacy','imported_at'=>date('Y-m-d H:i:s')]);
            if ($this->app->fetchOne('SELECT 1 FROM crm_deals LIMIT 1') !== false || $this->app->fetchOne('SELECT 1 FROM crm_tasks LIMIT 1') !== false) throw new \DomainException('CRM уже содержит новые записи. Импорт не заменяет их.');
            $deals = $this->legacy->fetchAllAssociative('SELECT * FROM sklad_requests ORDER BY requestID');
            $dealIds = array_fill_keys(array_column($deals,'requestID'),true);
            foreach ($deals as $r) $this->app->insert('crm_deals',[
                'id'=>$r['requestID'],'customer_id'=>$r['customerID'],'author_id'=>$r['userID'],'stacker_id'=>$r['stackerID'],'source_id'=>$r['customer_source'],
                'comment'=>$this->text($r['comment']),'director_comment'=>$this->text($r['director_comment']),'budget'=>$r['budget'],'markup'=>$r['markup'],
                'shipping_address'=>$r['shipping_address'],'desired_at'=>$this->date($r['desired_date']),'created_at'=>$this->date($r['request_time']) ?? date('Y-m-d H:i:s'),
                'state'=>$r['hidden'] ? 'hidden' : ($r['cancelled'] ? 'cancelled' : 'active'),
            ]);
            foreach ($this->legacy->fetchAllAssociative('SELECT * FROM sklad_request_cards ORDER BY id') as $r) {
                if (!isset($dealIds[$r['requestID']])) continue;
                $this->app->executeStatement('INSERT INTO crm_deal_items (id,deal_id,product_id,quantity,price) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)',[$r['id'],$r['requestID'],$r['productID'],$r['quantity'] ?? '0',$r['price'] ?? '0']);
            }
            $orphanContacts = 0;
            foreach ($this->legacy->fetchAllAssociative('SELECT * FROM sklad_request_tasks ORDER BY id') as $r) {
                if (!isset($dealIds[$r['requestID']])) { ++$orphanContacts; continue; }
                $this->app->insert('crm_deal_contacts',['id'=>$r['id'],'deal_id'=>$r['requestID'],'author_id'=>$r['userID'],'executor_id'=>$r['executor'],
                    'due_at'=>$this->date($r['date']) ?? date('Y-m-d H:i:s'),'comment'=>$this->text($r['text']),
                    'completed_at'=>$r['done'] ? ($this->date($r['date_completion']) ?? $this->date($r['date'])) : null]);
            }
            foreach ($this->legacy->fetchAllAssociative('SELECT * FROM sklad_request_log ORDER BY id') as $r) $this->app->insert('crm_deal_history',[
                'id'=>$r['id'],'deal_id'=>isset($dealIds[$r['requestID']]) ? $r['requestID'] : null,'legacy_deal_id'=>$r['requestID'],
                'author_id'=>$r['userID'],'happened_at'=>$this->date($r['date']) ?? date('Y-m-d H:i:s'),'details'=>$this->history($r['text']),
                'cancel_comment'=>$this->text($r['cancelComment']),'cancel_type'=>$r['cancelType'],
            ]);
            $tasks = $this->legacy->fetchAllAssociative('SELECT * FROM sklad_crm_tasks ORDER BY id');
            $taskIds = array_fill_keys(array_column($tasks,'id'),true); $missingFiles = 0;
            foreach ($tasks as $r) {
                $this->app->insert('crm_tasks',[
                    'id'=>$r['id'],'author_id'=>$r['userID'],'executor_id'=>$r['executor'],'created_at'=>$this->date($r['date']) ?? date('Y-m-d H:i:s'),
                    'due_at'=>$this->date($r['execute_date']),'text'=>$this->text($r['text']),'director_comment'=>$this->text($r['director_comment']),
                    'last_comment'=>$this->text($r['last_comment']),'completed_at'=>$this->date($r['completion_date']),'cancelled'=>(int)(bool)$r['cancelled'],'hidden'=>(int)(bool)$r['hidden'],
                ]);
                foreach (['co_executors'=>'coexecutor','observers'=>'observer'] as $field=>$kind) foreach ($this->ids($r[$field]) as $userId) {
                    $this->app->executeStatement('INSERT IGNORE INTO crm_task_participants(task_id,user_id,kind) VALUES (?,?,?)',[$r['id'],$userId,$kind]);
                }
                foreach (json_decode($r['files'] ?? '[]',true) ?: [] as $file) {
                    if (!is_string($file) || basename($file) !== $file) continue;
                    $source = rtrim($filesDir,'/').'/'.$r['id'].'/'.$file;
                    $storage = 'legacy-'.$r['id'].'-'.hash('sha256',$file); $available = is_file($source);
                    if ($available) {
                        $directory = $this->projectDir.'/var/crm-files';
                        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Не удалось создать хранилище файлов CRM.');
                        if (!copy($source,$directory.'/'.$storage)) throw new \RuntimeException('Не удалось перенести файл CRM.');
                    } else ++$missingFiles;
                    $this->app->insert('crm_task_files',['task_id'=>$r['id'],'original_name'=>$file,'storage_name'=>$storage,'size'=>$available ? filesize($source) : 0,'available'=>(int)$available,'created_at'=>date('Y-m-d H:i:s')]);
                }
            }
            foreach ($this->legacy->fetchAllAssociative('SELECT * FROM sklad_crm_tasks_log ORDER BY id') as $r) {
                if (isset($taskIds[$r['task_id']])) $this->app->insert('crm_task_history',['id'=>$r['id'],'task_id'=>$r['task_id'],'author_id'=>$r['userID'],'happened_at'=>$this->date($r['date']) ?? date('Y-m-d H:i:s'),'details'=>$this->history($r['text'])]);
            }
            foreach ($this->legacy->fetchAllAssociative('SELECT * FROM sklad_crm_tasks_notifications ORDER BY id') as $r) if (isset($taskIds[$r['taskID']])) {
                $this->app->executeStatement('INSERT IGNORE INTO crm_task_notifications(task_id,user_id) VALUES (?,?)',[$r['taskID'],$r['userID']]);
            }
            foreach ($this->legacy->fetchAllAssociative('SELECT * FROM sklad_calls_visitors ORDER BY dateID') as $r) {
                $date = \DateTimeImmutable::createFromFormat('!ymd',str_pad((string)$r['dateID'],6,'0',STR_PAD_LEFT));
                if ($date) $this->app->insert('crm_counters',['day'=>$date->format('Y-m-d'),'calls'=>$r['calls'],'new_calls'=>$r['new_calls'],'visitors'=>$r['visitors'],'new_visitors'=>$r['new_visitors']]);
            }
            return ['deals'=>count($deals),'tasks'=>count($tasks),'orphanContacts'=>$orphanContacts,'missingFiles'=>$missingFiles];
        });
    }
    private function date(?string $value): ?string { return !$value || str_starts_with($value,'0000-') ? null : $value; }
    private function ids(?string $json): array { return array_values(array_unique(array_filter(array_map('intval',json_decode($json ?? '[]',true) ?: []),static fn (int $id): bool => $id > 0))); }
    private function text(?string $value): string { return html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i',"\n",$value ?? '')),ENT_QUOTES|ENT_HTML5,'UTF-8'); }
    private function history(?string $value): string {
        $data = @unserialize($value ?? '',['allowed_classes'=>false]);
        $lines = is_array($data) ? $data : [$value ?? ''];
        $strings = []; array_walk_recursive($lines,function ($line) use (&$strings): void { if (is_scalar($line)) $strings[] = $this->text((string)$line); });
        return json_encode($strings,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
}
