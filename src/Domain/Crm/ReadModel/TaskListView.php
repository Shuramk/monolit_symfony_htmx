<?php declare(strict_types=1);

namespace App\Domain\Crm\ReadModel;

use App\Domain\Crm\ReadModel\Dtos\TaskFilterDto;
use App\Domain\Crm\Repository\CrmRepository;

final readonly class TaskListView {
    public function __construct(private CrmRepository $repository) {}

    public function get(int $actor, bool $director, bool $archive, TaskFilterDto $filter): array {
        return [
            'page' => $this->repository->tasks($actor, $director, $archive, $filter),
            'users' => $this->repository->users(),
            'director' => $director,
            'archive' => $archive,
            'scope' => $filter->scope,
            'state' => $filter->state,
            'executor' => $filter->executorId,
            'author' => $filter->authorId,
            'q' => $filter->q,
        ];
    }
}
