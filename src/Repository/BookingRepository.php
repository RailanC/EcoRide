<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return array<Booking>
     */
    public function findConfirmedParticipantsForTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('booking')
            ->leftJoin('booking.user', 'user')->addSelect('user')
            ->andWhere('booking.trip = :trip')
            ->andWhere('booking.confirmation = :confirmed')
            ->setParameter('trip', $trip)
            ->setParameter('confirmed', true)
            ->orderBy('booking.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findParticipantBookingForTrip(User $user, Trip $trip): ?Booking
    {
        return $this->createQueryBuilder('booking')
            ->leftJoin('booking.trip', 'trip')->addSelect('trip')
            ->leftJoin('trip.driver', 'driver')->addSelect('driver')
            ->andWhere('booking.user = :user')
            ->andWhere('booking.trip = :trip')
            ->andWhere('booking.confirmation = :confirmed')
            ->setParameter('user', $user)
            ->setParameter('trip', $trip)
            ->setParameter('confirmed', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<Booking>
     */
    public function findPendingOutcomeBookingsForUser(User $user): array
    {
        return $this->createQueryBuilder('booking')
            ->leftJoin('booking.trip', 'trip')->addSelect('trip')
            ->leftJoin('trip.driver', 'driver')->addSelect('driver')
            ->andWhere('booking.user = :user')
            ->andWhere('booking.confirmation = :confirmed')
            ->andWhere('booking.outcomeStatus = :outcomeStatus')
            ->andWhere('trip.status = :tripStatus')
            ->setParameter('user', $user)
            ->setParameter('confirmed', true)
            ->setParameter('outcomeStatus', Booking::OUTCOME_PENDING)
            ->setParameter('tripStatus', Trip::STATUS_ARRIVED)
            ->orderBy('trip.departureDate', 'DESC')
            ->addOrderBy('trip.departureTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Booking>
     */
    public function findPassengerTripHistoryForUser(User $user): array
    {
        return $this->createQueryBuilder('booking')
            ->leftJoin('booking.trip', 'trip')->addSelect('trip')
            ->leftJoin('trip.driver', 'driver')->addSelect('driver')
            ->andWhere('booking.user = :user')
            ->andWhere('booking.confirmation = :confirmed')
            ->setParameter('user', $user)
            ->setParameter('confirmed', true)
            ->orderBy('trip.departureDate', 'DESC')
            ->addOrderBy('trip.departureTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Booking>
     */
    public function findReviewableBookingsForUser(User $user): array
    {
        return $this->createQueryBuilder('booking')
            ->leftJoin('booking.trip', 'trip')->addSelect('trip')
            ->leftJoin('trip.driver', 'driver')->addSelect('driver')
            ->andWhere('booking.user = :user')
            ->andWhere('booking.confirmation = :confirmed')
            ->andWhere('booking.outcomeStatus = :outcomeStatus')
            ->andWhere('trip.status IN (:tripStatuses)')
            ->setParameter('user', $user)
            ->setParameter('confirmed', true)
            ->setParameter('outcomeStatus', Booking::OUTCOME_CONFIRMED_GOOD)
            ->setParameter('tripStatuses', [Trip::STATUS_COMPLETED, Trip::STATUS_DISPUTED])
            ->orderBy('trip.departureDate', 'DESC')
            ->addOrderBy('trip.departureTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
