<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\User;
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
            ->setParameter('status', Review::STATUS_APPROVED)
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
            ->setParameter('status', Review::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByTripAuthorAndDriver(int $tripId, User $author, User $driver): ?Review
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.trip = :tripId')
            ->andWhere('review.author = :author')
            ->andWhere('review.user = :driver')
            ->setParameter('tripId', $tripId)
            ->setParameter('author', $author)
            ->setParameter('driver', $driver)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<Review>
     */
    public function findPendingReviews(): array
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.trip', 'trip')->addSelect('trip')
            ->leftJoin('review.author', 'author')->addSelect('author')
            ->leftJoin('review.user', 'driver')->addSelect('driver')
            ->andWhere('review.status = :status')
            ->setParameter('status', Review::STATUS_PENDING)
            ->orderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Review>
     */
    public function findAllReviews(): array
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.trip', 'trip')->addSelect('trip')
            ->leftJoin('review.author', 'author')->addSelect('author')
            ->leftJoin('review.user', 'driver')->addSelect('driver')
            ->leftJoin('review.moderatedBy', 'moderator')->addSelect('moderator')
            ->orderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Review>
     */
    public function findAuthoredReviewsForUser(User $author): array
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.trip', 'trip')->addSelect('trip')
            ->leftJoin('review.user', 'driver')->addSelect('driver')
            ->leftJoin('review.moderatedBy', 'moderator')->addSelect('moderator')
            ->andWhere('review.author = :author')
            ->setParameter('author', $author)
            ->orderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Review>
     */
    public function findReceivedReviewsForDriver(User $driver): array
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.trip', 'trip')->addSelect('trip')
            ->leftJoin('review.author', 'author')->addSelect('author')
            ->leftJoin('review.moderatedBy', 'moderator')->addSelect('moderator')
            ->andWhere('review.user = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
