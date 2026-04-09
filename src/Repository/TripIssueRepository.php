<?php

namespace App\Repository;

use App\Entity\TripIssue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TripIssue>
 */
class TripIssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TripIssue::class);
    }

    /**
     * @return array<TripIssue>
     */
    public function findOpenIssues(): array
    {
        return $this->createQueryBuilder('issue')
            ->leftJoin('issue.trip', 'trip')->addSelect('trip')
            ->leftJoin('issue.participant', 'participant')->addSelect('participant')
            ->leftJoin('issue.driver', 'driver')->addSelect('driver')
            ->andWhere('issue.status = :status')
            ->setParameter('status', TripIssue::STATUS_OPEN)
            ->orderBy('issue.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<TripIssue>
     */
    public function findAllIssues(): array
    {
        return $this->createQueryBuilder('issue')
            ->leftJoin('issue.trip', 'trip')->addSelect('trip')
            ->leftJoin('issue.participant', 'participant')->addSelect('participant')
            ->leftJoin('issue.driver', 'driver')->addSelect('driver')
            ->leftJoin('issue.resolvedBy', 'resolvedBy')->addSelect('resolvedBy')
            ->orderBy('issue.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
