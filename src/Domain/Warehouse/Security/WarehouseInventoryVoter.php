<?php declare(strict_types=1);
namespace App\Domain\Warehouse\Security;

use App\Domain\Identity\Entities\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class WarehouseInventoryVoter extends Voter {
    public const string ATTRIBUTE = 'WAREHOUSE_INVENTORY';
    protected function supports(string $attribute, mixed $subject): bool {
        return $attribute === self::ATTRIBUTE;
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
        $user = $token->getUser();
        return $user instanceof User && $user->canPrintWarehouseInventory();
    }
}
