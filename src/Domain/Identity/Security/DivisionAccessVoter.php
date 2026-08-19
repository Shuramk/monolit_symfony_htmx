<?php

declare(strict_types=1);

namespace App\Domain\Identity\Security;

use App\Domain\Identity\Entities\User;

use App\Domain\Identity\Repository\IdentityMenuRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class DivisionAccessVoter extends Voter {
    public const string ATTRIBUTE = "LEGACY_DIVISION";

    public function __construct(private readonly IdentityMenuRepository $identityMenuRepository) {
    }

    protected function supports(string $attribute, mixed $subject): bool {
        return $attribute === self::ATTRIBUTE
            && (is_int($subject) || (is_string($subject) && ctype_digit($subject)));
    }

    protected function voteOnAttribute(string $attribute, mixed $divisionId, TokenInterface $token, ?Vote $vote = null): bool {
        $user = $token->getUser();

        if (!$user instanceof User || !$user->canAccess()) {
            return false;
        }

        return $this->identityMenuRepository->userHasDivision($user->getId(), (int) $divisionId);
    }
}
