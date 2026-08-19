<?php declare(strict_types=1);

namespace App\Application\Controller\Crm;

use App\Domain\Crm\Entities\Task;
use App\Domain\Crm\ReadModel\CrmView;
use App\Domain\Crm\ReadModel\Dtos\TaskFilterDto;
use App\Domain\Crm\ReadModel\TaskListView;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Crm\UseCases\SaveTask;
use App\Domain\Crm\UseCases\TaskActions;
use App\Domain\Crm\UseCases\Dtos\SaveTaskDto;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Security\DivisionAccessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController extends AbstractController {
    #[Route(
        '/crm/tasks',
        name: 'crm_tasks',
        defaults: ['director' => false, 'archive' => false, 'permission' => 1750],
        methods: ['GET'],
    )]
    #[Route(
        '/crm/tasks/archive',
        name: 'crm_tasks_archive',
        defaults: ['director' => false, 'archive' => true, 'permission' => 1754],
        methods: ['GET'],
    )]
    #[Route(
        '/crm/director/tasks',
        name: 'crm_director_tasks',
        defaults: ['director' => true, 'archive' => false, 'permission' => 1755],
        methods: ['GET'],
    )]
    #[Route(
        '/crm/director/tasks/archive',
        name: 'crm_director_archive',
        defaults: ['director' => true, 'archive' => true, 'permission' => 1759],
        methods: ['GET'],
    )]
    public function list(
        TaskFilterDto $filter,
        User $user,
        TaskListView $view,
        bool $director,
        bool $archive,
        int $permission,
    ): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, $permission);

        return $this->render(
            'crm/tasks.html.twig',
            $view->get($user->getId(), $director, $archive, $filter),
        );
    }

    #[Route('/crm/tasks/new', name: 'crm_task_new', methods: ['GET'])]
    #[Route('/crm/director/tasks/new', name: 'crm_director_task_new', methods: ['GET'])]
    public function new(Request $request, CrmView $view): Response {
        $director = str_starts_with($request->getPathInfo(),'/crm/director/');
        $this->checkSection($director);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,$director ? 1756 : 1751);

        return $this->render('crm/task_edit.html.twig',$view->options()+['task'=>null,'director'=>$director]);
    }
    #[Route('/crm/tasks/{id}', name: 'crm_task_show',requirements: ['id'=>'\d+'],methods: ['GET'])]
    #[Route('/crm/director/tasks/{id}', name: 'crm_director_task_show',requirements: ['id'=>'\d+'],methods: ['GET'])]
    public function show(int $id, Request $request, User $user, CrmView $view, CrmRepository $repository): Response {
        $director = str_starts_with($request->getPathInfo(),'/crm/director/'); $this->checkSection($director);
        $task = $view->task($id);
        Task::fromRow($task)->assertVisible($user->getId(),$director);
        $repository->clearNotifications($id,$user->getId());

        return $this->render('crm/task_edit.html.twig',$view->options()+compact('task','director'));
    }
    #[Route('/crm/tasks', name: 'crm_task_create', methods: ['POST'])]
    #[Route('/crm/director/tasks', name: 'crm_director_task_create', methods: ['POST'])]
    public function create(Request $request, SaveTaskDto $dto, SaveTask $save, User $user): Response {
        $director = str_starts_with($request->getPathInfo(),'/crm/director/'); $this->checkSection($director);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,$director ? 1756 : 1751);
        $id = $save->execute(null,$dto,$user->getId(),$director,$user->getRole() === UserRole::Main,$this->now());

        return $this->result($id,$director);
    }
    #[Route('/crm/tasks/{id}', name: 'crm_task_save',requirements: ['id'=>'\d+'],methods: ['POST'])]
    #[Route('/crm/director/tasks/{id}', name: 'crm_director_task_save',requirements: ['id'=>'\d+'],methods: ['POST'])]
    public function save(int $id, Request $request, SaveTaskDto $dto, SaveTask $save, User $user): Response {
        $director = str_starts_with($request->getPathInfo(),'/crm/director/'); $this->checkSection($director);
        $save->execute($id,$dto,$user->getId(),$director,$user->getRole() === UserRole::Main,$this->now());

        return $this->result($id,$director);
    }
    #[Route('/crm/tasks/{id}/{action}', name: 'crm_task_action',requirements: ['id'=>'\d+','action'=>'complete|reopen|cancel|hide|restore'],methods: ['POST'])]
    #[Route('/crm/director/tasks/{id}/{action}', name: 'crm_director_task_action',requirements: ['id'=>'\d+','action'=>'complete|reopen|cancel|hide|restore'],methods: ['POST'])]
    public function action(int $id, string $action, Request $request, TaskActions $actions, User $user): Response {
        $director = str_starts_with($request->getPathInfo(),'/crm/director/'); $this->checkSection($director); $data = $request->getPayload();
        $actions->execute($id,$action,$data->has('version') ? $data->getInt('version') : null,$user->getId(),$director,$user->getRole() === UserRole::Main,$data->getString('comment'),$this->now());

        return $this->result($id,$director);
    }

    private function checkSection(bool $director): void {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,$director ? 1755 : 1750);
    }

    private function result(int $id, bool $director): Response {
        return $this->json(['id'=>$id,'location'=>$this->generateUrl($director ? 'crm_director_task_show' : 'crm_task_show',['id'=>$id])]);
    }

    private function now(): \DateTimeImmutable {
        return new \DateTimeImmutable('now',new \DateTimeZone('Europe/Kyiv'));
    }
}
