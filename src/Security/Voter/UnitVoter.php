<?php

namespace App\Security\Voter;

use App\Entity\Unit;
use App\Entity\User;
use App\Repository\UnitAssignmentRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UnitVoter extends Voter
{
    public const VIEW = 'UNIT_VIEW';
    public const EDIT = 'UNIT_EDIT';
    public const DELETE = 'UNIT_DELETE';

    public function __construct(
        private Security $security,
        private UnitAssignmentRepository $assignments,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Unit;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Unit $unit */
        $unit = $subject;

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Advisors can view/edit units in their team
        if ($this->security->isGranted('ROLE_ADVISOR')) {
            return $unit->getTeam() && $user->getTeam() && $unit->getTeam()->getId() === $user->getTeam()->getId();
        }

        // Portal users: view only if assigned (and generating)
        if ($attribute !== self::VIEW) {
            return false;
        }

        $servant = $user->getServant();
        if (!$servant || !$unit->isGenerating()) {
            return false;
        }

        // quick exists check
        return (bool) $this->assignments->createQueryBuilder('ua')
            ->select('1')
            ->andWhere('ua.unit = :unit')
            ->andWhere('ua.servant = :servant')
            ->setParameter('unit', $unit)
            ->setParameter('servant', $servant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
