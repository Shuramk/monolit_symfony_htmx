<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases;

use App\Domain\Crm\Entities\Deal;
use App\Domain\Crm\Entities\Task;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Crm\UseCases\Dtos\SaveTaskDto;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class SaveTask {
    public function __construct(private CrmRepository $repository) {}
    public function execute(?int $id, SaveTaskDto $data, int $actor, bool $director, bool $main, \DateTimeImmutable $now): int {
        return $this->repository->transactional(function () use ($id,$data,$actor,$director,$main,$now): int {
            $old = $id === null ? null : $this->repository->task($id,true);
            if ($old) {
                $task = Task::fromRow($old); $task->assertVisible($actor,$director); $task->assertEditable(); $this->repository->checkVersion($old,$data->version);
                if (!$director && !$task->isExecutor($actor) && $task->authorId !== $actor) throw new AccessDeniedException('Наблюдатель не может изменять задачу.');
                if (!$director && $task->authorId !== $actor && trim($data->text) !== $old['text']) throw new AccessDeniedException('Описание задачи может менять автор.');
            }
            if (!$main && trim($data->directorComment) !== ($old['director_comment'] ?? '')) throw new AccessDeniedException('Комментарий руководителя может менять только Главная.');
            $this->repository->requireUser($data->executorId,$old ? (int)$old['executor_id'] : null);
            $coexecutors = $this->ids($data->coexecutors); $observers = $this->ids($data->observers);
            $coexecutors = array_values(array_diff($coexecutors,[$data->executorId]));
            $observers = array_values(array_diff($observers,[$data->executorId],$coexecutors));
            if ($old && (int)$old['executor_id'] !== $data->executorId && (int)$old['executor_id'] > 0) $observers[] = (int)$old['executor_id'];
            $observers = array_values(array_unique($observers));
            foreach (array_merge($coexecutors,$observers) as $user) $this->repository->requireUser($user,in_array($user,array_merge($old['coexecutors'] ?? [],$old['observers'] ?? [],[(int)($old['executor_id'] ?? 0)]),true) ? $user : null);
            $due = Deal::date($data->dueAt); if ($due === null) throw new \DomainException('Укажите срок выполнения.');
            $text = trim($data->text); if ($text === '') throw new \DomainException('Введите описание задачи.');
            $row = ['text'=>$text,'executor_id'=>$data->executorId,'due_at'=>$due,'director_comment'=>trim($data->directorComment),'last_comment'=>trim($data->lastComment)];
            $stamp = $now->format('Y-m-d H:i:s'); $changes = [];
            if ($id === null) { $id = $this->repository->createTask($row+['author_id'=>$actor,'created_at'=>$stamp]); $changes[] = 'Создана задача №'.$id; }
            else {
                foreach ($row as $field=>$value) if ((string)$value !== (string)$old[$field]) $changes[] = match ($field) { 'text'=>'Описание','executor_id'=>'Исполнитель','due_at'=>'Дата выполнения','director_comment'=>'Комментарий руководителя',default=>'Комментарий' }.': '.$old[$field].' → '.$value;
                $this->repository->updateTask($id,$row,(int)$old['version']);
            }
            if ($coexecutors !== ($old['coexecutors'] ?? []) || $observers !== ($old['observers'] ?? [])) $changes[] = 'Участники: соисполнители '.implode(', ',$coexecutors).'; наблюдатели '.implode(', ',$observers);
            $this->repository->participants($id,$coexecutors,$observers);
            $this->repository->history('task',$id,$actor,$changes,$stamp);
            if ($changes) $this->repository->notify($id,array_merge([$data->executorId,(int)($old['author_id'] ?? $actor)],$coexecutors,$observers),$actor);
            return $id;
        });
    }
    private function ids(array $values): array {
        $ids = [];
        foreach ($values as $value) {
            $value = is_array($value) ? ($value['id'] ?? null) : $value;
            if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value <= 0) throw new \DomainException('Неизвестный участник задачи.');
            $ids[] = (int)$value;
        }
        return array_values(array_unique($ids));
    }
}
