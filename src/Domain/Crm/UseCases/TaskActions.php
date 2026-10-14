<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases;

use App\Domain\Crm\Entities\Task;
use App\Domain\Crm\Repository\CrmRepository;

final readonly class TaskActions {
    public function __construct(private CrmRepository $repository) {}
    public function execute(int $id, string $action, ?int $version, int $actor, bool $director, bool $main, string $comment, \DateTimeImmutable $now): void {
        $this->repository->transactional(function () use ($id,$action,$version,$actor,$director,$main,$comment,$now): void {
            $row = $this->repository->task($id,true); $task = Task::fromRow($row);
            $task->assertVisible($actor,$director); $this->repository->checkVersion($row,$version);
            if (mb_strlen($comment) > 20000) throw new \DomainException('Комментарий слишком длинный.');
            $stamp = $now->format('Y-m-d H:i:s'); $task->action($action,$actor,$director,$main,$stamp);
            $data = $task->state(); if (trim($comment) !== '') $data['last_comment'] = trim($comment);
            $this->repository->updateTask($id,$data,$task->version);
            $message = match ($action) { 'complete'=>'Задача выполнена.','reopen'=>'Выполнение отменено.','cancel'=>'Задача отменена.','hide'=>'Задача скрыта.','restore'=>'Задача восстановлена.' };
            $this->repository->history('task',$id,$actor,[$message,trim($comment)],$stamp);
            $this->repository->clearNotifications($id);
            $users = in_array($action,['complete','cancel'],true) ? [(int)$task->authorId] : array_merge([(int)$task->authorId,(int)$task->executorId],$task->coexecutors,$task->observers);
            $this->repository->notify($id,$users,$actor);
        });
    }
}
