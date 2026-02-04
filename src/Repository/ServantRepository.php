<?php

namespace App\Repository;

use App\Entity\Servant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Servant>
 */
class ServantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Servant::class);
    }

    public function findWithGeneratingUnits(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.unitAssignments', 'ua')
            ->innerJoin('ua.unit', 'u')
            ->andWhere('u.isGenerating = :g')
            ->setParameter('g', true)
            ->distinct()
            ->orderBy('s.lastName1', 'ASC')
            ->addOrderBy('s.lastName2', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPortalAndStaff(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.unitAssignments', 'ua')
            ->leftJoin('ua.unit', 'u')
            ->leftJoin('s.user', 'usr') // only works if Servant has OneToOne inverse 'user'
            ->andWhere('(u.isGenerating = true) OR (usr.id IS NOT NULL)')
            ->distinct()
            ->orderBy('s.lastName1', 'ASC')
            ->addOrderBy('s.lastName2', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }



//    /**
//     * @return Servant[] Returns an array of Servant objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Servant
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
