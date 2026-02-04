<?php

namespace App\Repository;

use App\Entity\Servant;
use App\Entity\Unit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unit>
 */
class UnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unit::class);
    }

    public function findGenerating(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isGenerating = :g')
            ->setParameter('g', true)
            ->orderBy('u.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function createVisibleForUserQueryBuilder(User $user): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isGenerating = true')
            ->leftJoin('u.team', 't')->addSelect('t')
            ->leftJoin('u.subfondo', 'sf')->addSelect('sf')
            ->orderBy('u.code', 'ASC');

        $roles = $user->getRoles();

        // Admin: all generating units
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $qb;
        }

        // Advisors: generating units that belong to their team
        if (in_array('ROLE_ADVISOR', $roles, true)) {
            $team = $user->getTeam();
            if (!$team) {
                // no team assigned -> show none
                return $qb->andWhere('1 = 0');
            }

            return $qb
                ->andWhere('u.team = :team')
                ->setParameter('team', $team);
        }

        // Portal users: generating units where servant has assignments
        $servant = $user->getServant();
        if (!$servant) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->innerJoin('u.unitAssignments', 'ua')
            ->andWhere('ua.servant = :servant')
            ->setParameter('servant', $servant)
            ->distinct();
    }

    public function createSearchVisibleForUserQueryBuilder(User $user, ?string $q): QueryBuilder
    {
        $qb = $this->createVisibleForUserQueryBuilder($user);

        if ($q) {
            $qb->andWhere('u.code LIKE :q OR u.name LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        return $qb;
    }


    public function findVisibleForUser(User $user): array
    {
        return $this->createVisibleForUserQueryBuilder($user)
            ->getQuery()
            ->getResult();
    }

    public function findGeneratingRoots(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isGenerating = true')
            ->andWhere('u.parent IS NULL')
            ->orderBy('u.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findGeneratingChildren(Unit $parent): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isGenerating = true')
            ->andWhere('u.parent = :p')
            ->setParameter('p', $parent)
            ->orderBy('u.code', 'ASC')
            ->getQuery()
            ->getResult();
    }


    //    /**
    //     * @return Unit[] Returns an array of Unit objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Unit
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
