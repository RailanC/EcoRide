<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function getAverageRatingForUser(int $userId): ?float
    {
        return (float) $this->createQueryBuilder('review')
            ->select('AVG(review.rating)')
            ->andWhere('review.user = :userId')
            ->andWhere('review.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'VALIDE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getValidatedReviewCountForUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.user = :userId')
            ->andWhere('review.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'VALIDE')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
