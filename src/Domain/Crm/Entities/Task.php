<?php declare(strict_types=1);
namespace App\Domain\Crm\Entities;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class Task {
    public function __construct(public readonly int $id, public readonly ?int $authorId, public readonly ?int $executorId, public readonly array $coexecutors, public readonly array $observers, private ?string $completedAt, private bool $cancelled, private bool $hidden, public readonly int $version) {}
    public static function fromRow(array $row): self { return new self((int)$row['id'],$row['author_id'] === null ? null : (int)$row['author_id'],$row['executor_id'] === null ? null : (int)$row['executor_id'],$row['coexecutors'] ?? [],$row['observers'] ?? [],$row['completed_at'],(bool)$row['cancelled'],(bool)$row['hidden'],(int)$row['version']); }
    public function isParticipant(int $id): bool { return $id === $this->authorId || $this->isExecutor($id) || in_array($id,$this->observers,true); }
    public function isExecutor(int $id): bool { return $id === $this->executorId || in_array($id,$this->coexecutors,true); }
    public function assertVisible(int $id, bool $director): void { if (!$director && !$this->isParticipant($id)) throw new AccessDeniedException('Нет доступа к этой задаче.'); }
    public function assertEditable(): void { if ($this->completedAt !== null || $this->cancelled || $this->hidden) throw new \DomainException('Завершённую, отменённую или скрытую задачу нельзя редактировать.'); }
    public function action(string $action, int $actor, bool $director, bool $main, string $now): void {
        $this->assertVisible($actor,$director);
        switch ($action) {
            case 'complete':
                if (!$director && !$this->isExecutor($actor)) throw new AccessDeniedException('Выполнять задачу может исполнитель.');
                $this->assertEditable(); $this->completedAt = $now; break;
            case 'reopen':
                if (!$director && !$this->isExecutor($actor) && $actor !== $this->authorId) throw new AccessDeniedException('Нет права вернуть задачу.');
                if ($this->completedAt === null || $this->cancelled || $this->hidden) throw new \DomainException('Задача не выполнена.');
                $this->completedAt = null; break;
            case 'cancel':
                if (!$director && $actor !== $this->authorId) throw new AccessDeniedException('Отменять задачу может автор.');
                $this->assertEditable(); $this->cancelled = true; break;
            case 'hide':
                if (!$main) throw new AccessDeniedException('Скрывать задачу может только Главная.');
                if (!$this->cancelled) throw new \DomainException('Скрыть можно только отменённую задачу.');
                $this->hidden = true; break;
            case 'restore':
                if (!$main) throw new AccessDeniedException('Восстанавливать задачу может только Главная.');
                if (!$this->cancelled && !$this->hidden) throw new \DomainException('Задача не отменена.');
                $this->hidden = $this->cancelled = false; $this->completedAt = null; break;
            default: throw new \DomainException('Неизвестное действие.');
        }
    }
    public function state(): array { return ['completed_at'=>$this->completedAt,'cancelled'=>(int)$this->cancelled,'hidden'=>(int)$this->hidden]; }
}
