<?php

namespace App\Repository;

use App\Entity\Servant;
use App\Entity\UnitAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnitAssignment>
 */
class UnitAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnitAssignment::class);
    }


    public function findGeneratingForServant(Servant $servant): array
    {
        return $this->createQueryBuilder('ua')
            ->innerJoin('ua.unit', 'u')->addSelect('u')
            ->andWhere('u.isGenerating = true')
            ->andWhere('ua.servant = :servant')
            ->setParameter('servant', $servant)
            ->orderBy('u.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
//    /**
//     * @return UnitAssignment[] Returns an array of UnitAssignment objects
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

//    public function findOneBySomeField($value): ?UnitAssignment
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
