<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findAllExceptCurrent(User $currentUser): array
    {
        return $this->createQueryBuilder('u')
            ->where('u != :current')
            ->setParameter('current', $currentUser)
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
