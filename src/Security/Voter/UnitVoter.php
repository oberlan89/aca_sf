<?php

namespace App\Security\Voter;

use App\Entity\Unit;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class UnitVoter extends Voter
{
    public const VIEW = 'UNIT_VIEW';
    public const EDIT = 'UNIT_EDIT';
    public const DELETE = 'UNIT_DELETE';
    public const REQUEST_CHANGE = 'UNIT_REQUEST_CHANGE';
    public const CREATE = 'UNIT_CREATE';

    public function __construct(private Security $security) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return true; // no subject needed
        }

        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::REQUEST_CHANGE], true)
            && $subject instanceof Unit;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // CREATE: admin only
        if ($attribute === self::CREATE) {
            return $this->security->isGranted('ROLE_ADMIN');
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Unit $unit */
        $unit = $subject;

        // Admin: everything
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Advisor: VIEW + REQUEST_CHANGE, team-limited
        if ($this->security->isGranted('ROLE_ADVISOR')) {
            return in_array($attribute, [self::VIEW, self::REQUEST_CHANGE], true)
                && $this->sameTeam($user, $unit);
        }

        // Portal user: VIEW only, generating only, assigned only
        if ($attribute !== self::VIEW || !$unit->isGenerating()) {
            return false;
        }

        return $this->isAssignedToUnit($user, $unit);
    }

    private function sameTeam(User $user, Unit $unit): bool
    {
        $userTeamId = $user->getTeam()?->getId();
        $unitTeamId = $unit->getTeam()?->getId();

        return $userTeamId !== null && $unitTeamId !== null && $userTeamId === $unitTeamId;
    }

    private function isAssignedToUnit(User $user, Unit $unit): bool
    {
        $servantId = $user->getServant()?->getId();
        if ($servantId === null) {
            return false;
        }

        // Doctrine collections support exists(); avoids manual foreach
        return $unit->getUnitAssignments()->exists(
            fn ($key, $ua) => $ua->getServant()?->getId() === $servantId
        );
    }
}
