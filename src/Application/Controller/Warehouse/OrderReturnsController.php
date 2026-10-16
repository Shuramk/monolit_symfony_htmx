<?php declare(strict_types=1);

namespace App\Application\Controller\Warehouse;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Order\ReadModel\OrderReturnsView;
use App\Domain\Order\UseCases\Dtos\OrderReturnDto;
use App\Domain\Order\UseCases\OrderReturns;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderReturnsController extends AbstractController {
    #[Route('/warehouse/returns', name: 'order_returns', defaults: ['archive' => false], methods: ['GET'])]
    #[Route('/warehouse/returns/archive', name: 'order_returns_archive', defaults: ['archive' => true], methods: ['GET'])]
    public function index(Request $request, bool $archive, OrderReturnsView $view, User $user): Response {
        $drafts = $this->isGranted(DivisionAccessVoter::ATTRIBUTE, 114);
        $returns = $this->isGranted(DivisionAccessVoter::ATTRIBUTE, 102);
        if ((!$drafts && !$returns) || ($archive && !$drafts)) {
            throw $this->createAccessDeniedException();
        }

        $create = $drafts && $this->isGranted(DivisionAccessVoter::ATTRIBUTE, 115);

        return $this->render(
            'order_returns/index.html.twig',
            [
                'page' => $view->page($archive, $request->query->getInt('page', 1), $drafts, $returns, $create),
                'canCreate' => $create,
                'canAccept' => $returns && $this->canAccept($user),
                'operationId' => $view->operationId()
            ]
        );
    }

    #[Route('/warehouse/returns/drafts', name: 'order_return_draft_create', methods: ['POST'])]
    public function draft(OrderReturnDto $dto, User $user, OrderReturns $useCase): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 114);
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 115);

        return $this->json(['id' => $useCase->draft($dto, $user->getId(), new \DateTimeImmutable()), 'location' => $this->generateUrl('order_returns')]);
    }

    #[Route('/warehouse/returns/select-order', name: 'order_return_select_order', methods: ['GET'])]
    public function selectOrder(Request $request, User $user): Response {
        $this->requireAccept($user);
        $id = $request->query->getInt('orderId');
        if ($id <= 0) {
            throw new \DomainException('Укажите номер заказа.');
        }

        return $this->redirectToRoute('order_return_new', ['id' => $id, 'draftId' => $request->query->getInt('draftId') ?: null]);
    }

    #[Route('/orders/{id}/returns/new', name: 'order_return_new', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(int $id, Request $request, User $user, OrderReturnsView $view): Response {
        $this->requireAccept($user);

        return $this->render(
            'order_returns/form.html.twig',
            [
                'form' => $view->form($id, $request->query->getInt('draftId') ?: null),
                'operationId' => $view->operationId()
            ]
        );
    }

    #[Route('/orders/{id}/returns', name: 'order_return_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(int $id, OrderReturnDto $dto, User $user, OrderReturns $useCase): Response {
        $this->requireAccept($user);
        $returnId = $useCase->accept($id, $dto, $user->getId(), new \DateTimeImmutable());

        return $this->json([
            'id' => $returnId,
            'location' => $this->isGranted(DivisionAccessVoter::ATTRIBUTE, 220)
                ? $this->generateUrl('order_show', ['id' => $id])
                : $this->generateUrl('order_returns')
        ]);
    }

    private function canAccept(User $user): bool {
        return in_array($user->getRole(), [UserRole::Main, UserRole::Sales, UserRole::Production], true)
            && $this->isGranted(DivisionAccessVoter::ATTRIBUTE, 103);
    }

    private function requireAccept(User $user): void {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 102);
        if (!$this->canAccept($user)) {
            throw $this->createAccessDeniedException();
        }
    }
}
