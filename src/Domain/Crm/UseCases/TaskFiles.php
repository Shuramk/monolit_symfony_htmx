<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases;

use App\Domain\Crm\Entities\Task;
use App\Domain\Crm\Repository\CrmRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class TaskFiles {
    public const MAX_SIZE = 10485760;
    public function __construct(private CrmRepository $repository, #[Autowire('%kernel.project_dir%')] private string $projectDir) {}
    public function upload(int $id, array $files, ?int $version, int $actor, bool $director): void {
        if (!$files || count($files) > 20) throw new \DomainException('Выберите от 1 до 20 файлов.');
        foreach ($files as $file) if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > self::MAX_SIZE) throw new \DomainException('Файл не загружен или больше 10 МБ.');
        $written = [];
        try {
            $this->repository->transactional(function () use ($id,$files,$version,$actor,$director,&$written): void {
                $row = $this->authorize($id,$actor,$director,true); $this->repository->checkVersion($row,$version);
                $directory = $this->projectDir.'/var/crm-files';
                if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Хранилище CRM недоступно.');
                $names = []; $now = date('Y-m-d H:i:s');
                foreach ($files as $file) {
                    $name = basename(str_replace('\\','/',$file->getClientOriginalName()));
                    if ($name === '' || mb_strlen($name) > 255 || preg_match('/[\x00-\x1f]/',$name)) throw new \DomainException('Некорректное имя файла.');
                    $storage = bin2hex(random_bytes(24)); $mime = $file->getMimeType() ?: 'application/octet-stream'; $size = $file->getSize();
                    $file->move($directory,$storage); $written[] = $directory.'/'.$storage;
                    $this->repository->addFile(['task_id'=>$id,'original_name'=>$name,'storage_name'=>$storage,'mime'=>$mime,'size'=>$size,'created_at'=>$now]); $names[] = $name;
                }
                $this->repository->updateTask($id,[],(int)$row['version']);
                $this->repository->history('task',$id,$actor,['Добавлены файлы: '.implode(', ',$names)],$now);
                $this->repository->notify($id,array_merge([(int)$row['author_id'],(int)$row['executor_id']],$row['coexecutors'],$row['observers']),$actor);
            });
        } catch (\Throwable $e) { foreach ($written as $path) if (is_file($path)) unlink($path); throw $e; }
    }
    public function download(int $task, int $file, int $actor, bool $director): array {
        $this->authorize($task,$actor,$director,false); $row = $this->repository->file($task,$file);
        if (!$row['available'] || basename($row['storage_name']) !== $row['storage_name']) throw new NotFoundHttpException('Файл отсутствует в хранилище.');
        $path = $this->projectDir.'/var/crm-files/'.$row['storage_name'];
        if (!is_file($path)) throw new NotFoundHttpException('Файл отсутствует в хранилище.');
        return ['path'=>$path,'name'=>$row['original_name']];
    }
    public function remove(int $task, int $file, ?int $version, int $actor, bool $director): void {
        $this->repository->transactional(function () use ($task,$file,$version,$actor,$director): void {
            $row = $this->authorize($task,$actor,$director,true); $this->repository->checkVersion($row,$version);
            $entry = $this->repository->file($task,$file); $this->repository->removeFile($file);
            $this->repository->updateTask($task,[],(int)$row['version']);
            $this->repository->history('task',$task,$actor,['Удалён файл: '.$entry['original_name']],date('Y-m-d H:i:s'));
        });
    }
    private function authorize(int $id, int $actor, bool $director, bool $write): array {
        $row = $this->repository->task($id,$write); $task = Task::fromRow($row); $task->assertVisible($actor,$director);
        if ($write) {
            $task->assertEditable();
            if (!$director && $task->authorId !== $actor && !$task->isExecutor($actor)) throw new AccessDeniedException('Наблюдатель не может изменять файлы.');
        }
        return $row;
    }
}
