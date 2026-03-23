<?php

namespace App\Repository;

use App\Entity\Avis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function getAverageRatingForUser(int $userId): ?float
    {
        return (float) $this->createQueryBuilder('a')
            ->select('AVG(a.note)')
            ->andWhere('a.utilisateur = :userId')
            ->andWhere('a.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'VALIDE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getValidatedReviewCountForUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.utilisateur = :userId')
            ->andWhere('a.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'VALIDE')
            ->getQuery()
            ->getSingleScalarResult();
    }
    //    /**
    //     * @return Avis[] Returns an array of Avis objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Avis
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
