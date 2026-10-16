<?php declare(strict_types=1);

namespace App\Application\Controller\Crm;

use App\Domain\Crm\ReadModel\CrmView;
use App\Domain\Crm\ReadModel\CrmBoardView;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Crm\UseCases\DealActions;
use App\Domain\Crm\UseCases\ApproveShipment;
use App\Domain\Warehouse\UseCases\Dtos\ShippingApprovalDto;
use App\Domain\Warehouse\ReadModel\Dtos\ShippingFilterDto;
use App\Domain\Warehouse\ReadModel\ShippingView;
use App\Domain\Crm\UseCases\SaveDeal;
use App\Domain\Crm\UseCases\Dtos\SaveDealDto;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Security\DivisionAccessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm')]
#[IsGranted(DivisionAccessVoter::ATTRIBUTE,subject:new Expression('1700'))]
final class DealController extends AbstractController {
    #[Route('/deals', name: 'crm_deals', methods: ['GET'])]
    public function list(Request $request, CrmRepository $repository, CrmBoardView $view): Response {
        $q = trim($request->query->getString('q'));
        $managers = array_values(array_unique(array_filter(array_map('intval', $request->query->all('managers')), static fn (int $id): bool => $id > 0)));
        if ($request->query->getInt('manager') > 0) {
            $managers[] = $request->query->getInt('manager');
        }
        $state = $request->query->getString('state','active');
        if (!in_array($state, ['active', 'cancelled', 'hidden', 'converted'], true)) {
            $state = 'active';
        }
        $now = $this->now();

        return $this->render('crm/deals.html.twig', [
            'board' => $view->get($state, $q, $managers, $now),
            'state' => $state, 'q' => $q,
            'managers' => $managers,
            'users' => $repository->users(),
            'counters' => $repository->counters($now->format('Y-m-d')),
        ]);
    }

    #[Route('/deals/new', name: 'crm_deal_new', methods: ['GET'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE,subject:new Expression('1701'))]
    public function new(CrmView $view): Response {
        return $this->render('crm/deal_edit.html.twig', $view->form(null));
    }

    #[Route('/deals/{id}', name: 'crm_deal_show', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function show(int $id, CrmView $view): Response {
        return $this->render('crm/deal_edit.html.twig', $view->form($id));
    }

    #[Route('/deals', name: 'crm_deal_create', methods: ['POST'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE,subject:new Expression('1701'))]
    public function create(SaveDealDto $dto, SaveDeal $save, User $user): Response {
        $id = $save->execute(null, $dto, $user->getId(), $user->getRole() === UserRole::Main, $this->now());

        return $this->json([
            'id' => $id,
            'location' => $this->generateUrl('crm_deal_show', ['id'=>$id]),
        ]);
    }

    #[Route('/deals/{id}', name: 'crm_deal_save', requirements: ['id'=>'\d+'] ,methods: ['POST'])]
    public function save(int $id, SaveDealDto $dto, SaveDeal $save, DealActions $actions, CrmRepository $repository, User $user): Response {
        if ($dto->toOrder) {
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,225);
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,220);
        }
        $now = $this->now();
        $main = $user->getRole() === UserRole::Main;
        $order = $repository->transactional(function () use ($id, $dto, $save, $actions, $user, $now, $main): ?int {
            $save->execute($id,$dto,$user->getId(),$main,$now);
            if ($dto->toOrder) {
                return $actions->execute($id, 'convert', ($dto->version ?? 0) + 1, $user->getId(), $main, '', 0, $now);
            }

            return null;
        });

        return $this->json([
            'id' => $id,
            'location' => $order
                ? $this->generateUrl('order_edit', ['id' => $order])
                : $this->generateUrl('crm_deal_show', ['id' => $id]),
            ]);
    }

    #[Route('/deals/{id}/{action}', name: 'crm_deal_action', requirements: ['id'=>'\d+', 'action'=>'cancel|hide|restore|convert'], methods: ['POST'])]
    public function action(int $id, string $action, Request $request, DealActions $actions, User $user): Response {
        if ($action === 'convert') {
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,225);
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,220);
        }
        $data = $request->getPayload();
        $order = $actions->execute(
            $id,
            $action,
            $data->has('version') ? $data->getInt('version') : null,
            $user->getId(),
            $user->getRole() === UserRole::Main,
            $data->getString('comment'),
            $data->getInt('cancelType'),
            $this->now(),
        );

        return $this->json([
            'location' => $order && $action === 'convert'
                ? $this->generateUrl('order_edit', ['id' => $order])
                : $this->generateUrl('crm_deal_show', ['id' => $id]),
            ]);
    }

    #[Route('/counters', name: 'crm_counter', methods: ['POST'])]
    public function counter(Request $request, CrmRepository $repository): Response {
        $repository->incrementCounter($this->now()->format('Y-m-d'), $request->getPayload()->getString('kind'));

        return $this->json([
            'location' => $this->generateUrl('crm_deals'),
            'counters' => $repository->counters($this->now()->format('Y-m-d')),
        ]);
    }

    #[Route('/shipments/{id}/approval', name: 'crm_shipment_approval', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approval(
        int $id,
        ShippingApprovalDto $dto,
        ShippingFilterDto $filter,
        ApproveShipment $approve,
        User $user,
        ShippingView $view,
    ): Response {
        if (!$user->canApproveShipping()) {
            throw $this->createAccessDeniedException('Согласование отгрузки доступно продажам и главной группе.');
        }
        $approve->execute($id, $dto->allowed === 1, $dto->expectedAllowed === 1, $user->getId(), $this->now());

        return $this->json([
            'location' => $this->generateUrl(
                $dto->returnToShipping ? 'shipping_list' : 'crm_deals',
                $dto->returnToShipping ? $filter->parameters() : [],
            ),
            'task' => $dto->returnToShipping ? $view->state($id) : null,
        ]);
    }

    private function now(): \DateTimeImmutable {
        return new \DateTimeImmutable('now',new \DateTimeZone('Europe/Kyiv'));
    }
}
