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

    public function createVisibleForUserQueryBuilder(User $user = null): QueryBuilder
    {
        $queryBuilder = $this->addOrderedByCodeQueryBuilder()
            ->leftJoin('unit.team', 'team')
            ->leftJoin('unit.subfondo', 'subfondo')
            ->leftJoin('unit.unitAssignments', 'unitAssignment');

        $roles = $user->getRoles();

        // Admin: all generating units
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $queryBuilder;
        }

        // Advisors: generating units that belong to their team
        if (in_array('ROLE_ADVISOR', $roles, true)) {
            $team = $user->getTeam();
            if (!$team) {
                // no team assigned -> show none
                return $queryBuilder->andWhere('1 = 0');
            }

            return $queryBuilder->andWhere('team = :team')
                ->setParameter('team', $team);
        }

        // Portal users: generating units where servant has assignments
        $servant = $user->getServant();
        if (!$servant) {
            return $queryBuilder->andWhere('1 = 0');
        }

        return $queryBuilder->andWhere('unitAssignment.servant = :servant')
            ->setParameter('servant', $servant)
            ->distinct();
    }

    public function createSearchVisibleForUserQueryBuilder(User $user, ?string $q): QueryBuilder
    {
        $queryBuilder = $this->createVisibleForUserQueryBuilder($user)
            ->leftJoin('team.users', 'user')
            ->leftJoin('user.servant', 'servant')
            ->distinct();

        if ($q) {
            $q = trim((string) $q);

            $qLower = function_exists('mb_strtolower')
                ? mb_strtolower($q, 'UTF-8')
                : strtolower($q);

            $queryBuilder->andWhere("LOWER(unit.code) LIKE :q OR LOWER(subfondo.name) LIKE :q OR LOWER(unit.name) LIKE :q OR CONCAT('', team.number) LIKE :q OR LOWER(user.email) LIKE :q OR LOWER(servant.firstName) LIKE :q OR LOWER(servant.lastName1) LIKE :q OR LOWER(servant.lastName2) LIKE :q")
                ->setParameter('q', '%'.$qLower.'%');
        }

        return $queryBuilder;
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

    public function findGeneratingChildren(Unit $parent = null): array
    {
        $queryBuilder = $this->addOrderedByCodeQueryBuilder();

        if ($parent) {
            $queryBuilder->andWhere('unit.parent = :parent')
                ->setParameter('parent', $parent);
        }

        return $queryBuilder->getQuery()
            ->getResult();
    }

    private function addOrderedByCodeQueryBuilder(QueryBuilder $queryBuilder = null): QueryBuilder
    {
        $queryBuilder = $queryBuilder ?? $this->createQueryBuilder('unit');

        return $queryBuilder->orderBy('unit.code', 'ASC')
            ->andWhere('unit.isGenerating = true');
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
