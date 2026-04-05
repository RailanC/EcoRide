<?php

namespace App\Repository;

use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    public function searchAvailableTrips(
        ?string $departure,
        ?string $arrival,
        ?string $date,
        ?int $passengers = null,
        bool $eco = false,
        ?float $maxPrice = null,
        ?int $maxDuration = null,
        ?float $minRating = null
    ): array {
        $queryBuilder = $this->createQueryBuilder('trip')
            ->leftJoin('trip.vehicle', 'vehicle')
            ->leftJoin('trip.driver', 'driver')
            ->andWhere('trip.status != :canceledStatus')
            ->setParameter('canceledStatus', 'canceled')
            ->orderBy('trip.departureDate', 'ASC');

        if (!empty($departure)) {
            $queryBuilder->andWhere('trip.departureLocation LIKE :departure')
                ->setParameter('departure', '%' . $departure . '%');
        }

        if (!empty($arrival)) {
            $queryBuilder->andWhere('trip.arrivalLocation LIKE :arrival')
                ->setParameter('arrival', '%' . $arrival . '%');
        }

        if (!empty($date)) {
            $startOfDay = new \DateTime($date . ' 00:00:00');
            $endOfDay = new \DateTime($date . ' 23:59:59');

            $queryBuilder->andWhere('trip.departureDate BETWEEN :startOfDay AND :endOfDay')
                ->setParameter('startOfDay', $startOfDay)
                ->setParameter('endOfDay', $endOfDay);
        }

        if ($eco) {
            $queryBuilder->andWhere('vehicle.energyType = :energyType')
                ->setParameter('energyType', 'Electrique');
        }

        if ($maxPrice !== null) {
            $queryBuilder->andWhere('trip.pricePerPerson <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        $results = $queryBuilder->getQuery()->getResult();

        if ($minRating !== null) {
            $results = array_values(array_filter(
                $results,
                static function (Trip $trip) use ($minRating): bool {
                    $rating = $trip->getDriver()?->getAverageValidatedRating();

                    return $rating !== null && $rating >= $minRating;
                }
            ));
        }

        if ($passengers !== null) {
            $results = array_values(array_filter(
                $results,
                static fn (Trip $trip): bool => (int) $trip->getAvailableSeats() >= $passengers
            ));
        }

        if ($maxDuration !== null) {
            $results = array_values(array_filter(
                $results,
                static function (Trip $trip) use ($maxDuration): bool {
                    $duration = $trip->getDurationInMinutes();

                    return $duration !== null && $duration <= $maxDuration;
                }
            ));
        }

        return array_values($results);
    }

    public function findAvailableTrips(): array
    {
        return $this->createQueryBuilder('trip')
            ->leftJoin('trip.vehicle', 'vehicle')
            ->leftJoin('trip.driver', 'driver')
            ->andWhere('trip.availableSeats > 0')
            ->andWhere('trip.status != :canceledStatus')
            ->setParameter('canceledStatus', 'canceled')
            ->orderBy('trip.departureDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
