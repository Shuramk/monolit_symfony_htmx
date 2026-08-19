<?php declare(strict_types=1);

namespace App\Domain\Warehouse\Security;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repository\IdentityMenuRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class WarehouseOperationVoter extends Voter {
    public function __construct(private readonly IdentityMenuRepository $permissions) {}
    protected function supports(string $attribute, mixed $subject): bool {
        return in_array($attribute, ['WAREHOUSE_ADD','WAREHOUSE_DELETE','WAREHOUSE_MINIMUM','WAREHOUSE_BREAKDOWN','WAREHOUSE_PRODUCTION','WAREHOUSE_ACCEPT'], true);
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        return match ($attribute) {
            'WAREHOUSE_ADD' => $user->canPrintWarehouseInventory() && $this->permissions->userHasDivision($user->getId(), 106),
            'WAREHOUSE_DELETE' => $user->canManageWarehouse() && $this->permissions->userHasDivision($user->getId(), 106),
            'WAREHOUSE_MINIMUM' => $user->canManageWarehouse(),
            'WAREHOUSE_BREAKDOWN' => $user->canSeeWarehouseBreakdown(),
            'WAREHOUSE_PRODUCTION', 'WAREHOUSE_ACCEPT' => $this->permissions->userHasDivision($user->getId(), 320),
        };
    }
}
