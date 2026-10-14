<?php declare(strict_types=1);

namespace App\Application\Controller\Order;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Order\UseCases\ChangeOrderStatus;
use App\Domain\Order\UseCases\Dtos\ChangeOrderStatusDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('220'))]
#[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject: new Expression('229'))]
final class OrderStatusController extends AbstractController {
    #[Route('/orders/{id}/status', name: 'order_status_change', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function change(int $id, ChangeOrderStatusDto $dto, User $user, ChangeOrderStatus $useCase): Response {
        $dealId = $useCase->execute($id, $dto, $user->getId(), $user->getRole() === UserRole::Main, new \DateTimeImmutable());
        $location = $dealId !== null && $this->isGranted(DivisionAccessVoter::ATTRIBUTE, 1700)
            ? $this->generateUrl('crm_deal_show', ['id' => $dealId])
            : $this->generateUrl('order_show', ['id' => $id]);

        return $this->json(['id' => $id, 'dealId' => $dealId, 'location' => $location]);
    }
}
