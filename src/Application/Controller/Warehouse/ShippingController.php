<?php declare(strict_types=1);

namespace App\Application\Controller\Warehouse;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingFilterDto;
use App\Domain\Warehouse\ReadModel\ShippingView;
use App\Domain\Warehouse\UseCases\Dtos\ShippingActionDto;
use App\Domain\Warehouse\UseCases\Dtos\ShippingTaskDto;
use App\Domain\Warehouse\UseCases\ShippingTasks;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShippingController extends AbstractController {
    #[Route('/shipping/{id}/print/{kind}', name: 'shipping_print', requirements: ['id' => '\d+', 'kind' => 'invoice|priced|security'], methods: ['GET'])]
    public function print(int $id, string $kind, ShippingView $view, User $user): Response {
        if (!$this->isGranted(DivisionAccessVoter::ATTRIBUTE, 305) && !$this->isGranted(DivisionAccessVoter::ATTRIBUTE, 311)) {
            throw $this->createAccessDeniedException();
        }
        $document = $view->document($id, $kind);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, (int) $document->task['status'] === 1 ? 311 : 305);
        if ($user->getRole() === UserRole::Production && $user->getId() !== 31
            && (int) ($document->task['payload']['storage'] ?? 2) !== 2
        ) {
            throw $this->createAccessDeniedException();
        }
        $response = $this->render('shipping/print.html.twig', ['document' => $document]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
    #[Route('/shipping', name: 'shipping_list', defaults: ['archive' => false], methods: ['GET'])]
    #[Route('/shipping/archive', name: 'shipping_archive', defaults: ['archive' => true], methods: ['GET'])]
    public function list(ShippingFilterDto $filter, ShippingView $view, bool $archive, User $user): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, $archive ? 311 : 305);

        return $this->render('shipping/index.html.twig', [
            'page' => $view->page($filter, $archive, $user->getRole() === UserRole::Production && $user->getId() !== 31),
        ]);
    }

    #[Route('/orders/{id}/shipping/new', name: 'shipping_new', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function new(int $id, ShippingView $view): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 220);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 232);

        return $this->render('shipping/form.html.twig', ['form' => $view->form(orderId: $id)]);
    }

    #[Route('/orders/{id}/shipping', name: 'shipping_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(int $id, ShippingTaskDto $dto, User $user, ShippingTasks $useCase): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 220);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 310);
        if ($dto->allowed) {
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 1700);
            if (!$user->canApproveShipping()) {
                throw $this->createAccessDeniedException('Согласование отгрузки доступно продажам и главной группе.');
            }
        }
        $taskId = $useCase->create($id, $dto, $user->getId(), new \DateTimeImmutable());

        return $this->json(['id' => $taskId, 'location' => $this->generateUrl('order_show', ['id' => $id])]);
    }

    #[Route('/shipping/{id}/edit', name: 'shipping_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(int $id, ShippingView $view): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 307);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 309);

        return $this->render('shipping/form.html.twig', ['form' => $view->form(taskId: $id)]);
    }

    #[Route('/shipping/{id}', name: 'shipping_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, ShippingTaskDto $dto, User $user, ShippingTasks $useCase): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 309);
        $useCase->edit($id, $dto, $user->getId(), new \DateTimeImmutable());

        return $this->json(['id' => $id, 'location' => $this->generateUrl('shipping_list')]);
    }

    #[Route('/shipping/{id}/{action}', name: 'shipping_action', requirements: ['id' => '\d+', 'action' => 'collect|complete|delete'], methods: ['POST'])]
    public function action(
        int $id,
        string $action,
        ShippingActionDto $dto,
        ShippingFilterDto $filter,
        User $user,
        ShippingTasks $useCase,
        ShippingView $view,
    ): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, $action === 'delete' ? 308 : 306);
        if ($action !== 'delete' && !$user->canExecuteShipping()) {
            throw $this->createAccessDeniedException('Выполнение отгрузки доступно производству.');
        }
        $useCase->act(
            $id, $dto->version, $action, $user->getId(),
            $user->getRole() === UserRole::Main || $user->getId() === 31, new \DateTimeImmutable(),
        );

        return $this->json([
            'id' => $id,
            'location' => $this->generateUrl('shipping_list', $filter->parameters()),
            'task' => $action === 'collect' ? $view->state($id) : null,
        ]);
    }
}
