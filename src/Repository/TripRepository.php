<?php

namespace App\Repository;

use App\Entity\Trip;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public const HISTORY_TAB_ALL = 'all';
    public const HISTORY_TAB_ACTIVE = 'active';
    public const HISTORY_TAB_OLD = 'old';

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

    /**
     * @return array<Trip>
     */
    public function findDriverHistoryTrips(User $driver, string $tab, ?string $search = null): array
    {
        $queryBuilder = $this->createDriverHistoryQueryBuilder($driver, $tab);
        $this->applyHistorySearch($queryBuilder, $search);

        return array_values($queryBuilder->getQuery()->getResult());
    }

    /**
     * @return array<Trip>
     */
    public function findPassengerHistoryTrips(User $passenger, string $tab, ?string $search = null): array
    {
        $queryBuilder = $this->createPassengerHistoryQueryBuilder($passenger, $tab);
        $this->applyHistorySearch($queryBuilder, $search);

        return array_values($queryBuilder->getQuery()->getResult());
    }

    /**
     * @return array{all: int, active: int, old: int}
     */
    public function countDriverHistoryTrips(User $driver): array
    {
        return [
            self::HISTORY_TAB_ALL => $this->countDriverHistoryTripsForTab($driver, self::HISTORY_TAB_ALL),
            self::HISTORY_TAB_ACTIVE => $this->countDriverHistoryTripsForTab($driver, self::HISTORY_TAB_ACTIVE),
            self::HISTORY_TAB_OLD => $this->countDriverHistoryTripsForTab($driver, self::HISTORY_TAB_OLD),
        ];
    }

    /**
     * @return array{all: int, active: int, old: int}
     */
    public function countPassengerHistoryTrips(User $passenger): array
    {
        return [
            self::HISTORY_TAB_ALL => $this->countPassengerHistoryTripsForTab($passenger, self::HISTORY_TAB_ALL),
            self::HISTORY_TAB_ACTIVE => $this->countPassengerHistoryTripsForTab($passenger, self::HISTORY_TAB_ACTIVE),
            self::HISTORY_TAB_OLD => $this->countPassengerHistoryTripsForTab($passenger, self::HISTORY_TAB_OLD),
        ];
    }

    private function applyHistorySearch($queryBuilder, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);
            $searchConditions = $queryBuilder->expr()->orX(
                'LOWER(trip.departureLocation) LIKE :search',
                'LOWER(trip.arrivalLocation) LIKE :search',
                'LOWER(trip.status) LIKE :search',
                'LOWER(COALESCE(driver.username, \'\')) LIKE :search',
                'LOWER(COALESCE(vehicle.model, \'\')) LIKE :search',
                'LOWER(COALESCE(vehicle.energyType, \'\')) LIKE :search',
                'LOWER(COALESCE(brand.label, \'\')) LIKE :search'
            );

            $date = $this->parseSearchDate($search);

            if ($date instanceof \DateTimeImmutable) {
                $searchConditions->add('trip.departureDate = :searchDate');
                $searchConditions->add('trip.arrivalDate = :searchDate');
                $queryBuilder->setParameter(
                    'searchDate',
                    \DateTime::createFromImmutable($date->setTime(0, 0)),
                    Types::DATE_MUTABLE
                );
            }

            $queryBuilder
                ->andWhere($searchConditions)
                ->setParameter('search', '%' . $normalizedSearch . '%');
        }
    }

    private function countDriverHistoryTripsForTab(User $driver, string $tab): int
    {
        return (int) $this->createDriverHistoryQueryBuilder($driver, $tab, false)
            ->select('COUNT(trip.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPassengerHistoryTripsForTab(User $passenger, string $tab): int
    {
        return (int) $this->createPassengerHistoryQueryBuilder($passenger, $tab, false)
            ->select('COUNT(trip.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createDriverHistoryQueryBuilder(User $driver, string $tab, bool $withSelects = true)
    {
        $tab = $this->normalizeHistoryTab($tab);
        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $currentTime = $now->format('H:i:s');

        $queryBuilder = $this->createQueryBuilder('trip')
            ->leftJoin('trip.vehicle', 'vehicle')
            ->leftJoin('vehicle.brand', 'brand')
            ->leftJoin('trip.driver', 'driver')
            ->andWhere('trip.driver = :driver')
            ->setParameter('driver', $driver);

        if ($withSelects) {
            $queryBuilder->addSelect('vehicle', 'brand', 'driver');
        }

        if ($tab === self::HISTORY_TAB_ACTIVE) {
            $queryBuilder
                ->andWhere('trip.status != :canceledStatus')
                ->andWhere('(trip.departureDate > :today OR (trip.departureDate = :today AND trip.departureTime >= :currentTime))')
                ->setParameter('canceledStatus', 'canceled')
                ->setParameter('today', $today)
                ->setParameter('currentTime', $currentTime)
                ->orderBy('trip.departureDate', 'ASC')
                ->addOrderBy('trip.departureTime', 'ASC');

            return $queryBuilder;
        }

        if ($tab === self::HISTORY_TAB_OLD) {
            $queryBuilder
                ->andWhere('(trip.status = :canceledStatus OR trip.departureDate < :today OR (trip.departureDate = :today AND trip.departureTime < :currentTime))')
                ->setParameter('canceledStatus', 'canceled')
                ->setParameter('today', $today)
                ->setParameter('currentTime', $currentTime)
                ->orderBy('trip.departureDate', 'DESC')
                ->addOrderBy('trip.departureTime', 'DESC');

            return $queryBuilder;
        }

        $queryBuilder
            ->orderBy('trip.departureDate', 'DESC')
            ->addOrderBy('trip.departureTime', 'DESC');

        return $queryBuilder;
    }

    private function createPassengerHistoryQueryBuilder(User $passenger, string $tab, bool $withSelects = true)
    {
        $tab = $this->normalizeHistoryTab($tab);
        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $currentTime = $now->format('H:i:s');
        $entityManager = $this->getEntityManager();
        $bookingSubquery = $entityManager->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\Booking', 'booking_history')
            ->andWhere('booking_history.trip = trip')
            ->andWhere('booking_history.user = :passenger')
            ->andWhere('booking_history.confirmation = :confirmed');
        $expr = $this->createQueryBuilder('trip_expr')->expr();

        $queryBuilder = $this->createQueryBuilder('trip')
            ->leftJoin('trip.vehicle', 'vehicle')
            ->leftJoin('vehicle.brand', 'brand')
            ->leftJoin('trip.driver', 'driver')
            ->andWhere($expr->exists($bookingSubquery->getDQL()))
            ->setParameter('passenger', $passenger)
            ->setParameter('confirmed', true);

        if ($withSelects) {
            $queryBuilder->addSelect('vehicle', 'brand', 'driver');
        }

        if ($tab === self::HISTORY_TAB_ACTIVE) {
            $queryBuilder
                ->andWhere('trip.status != :canceledStatus')
                ->andWhere('(trip.departureDate > :today OR (trip.departureDate = :today AND trip.departureTime >= :currentTime))')
                ->setParameter('canceledStatus', 'canceled')
                ->setParameter('today', $today)
                ->setParameter('currentTime', $currentTime)
                ->orderBy('trip.departureDate', 'ASC')
                ->addOrderBy('trip.departureTime', 'ASC');

            return $queryBuilder;
        }

        if ($tab === self::HISTORY_TAB_OLD) {
            $queryBuilder
                ->andWhere('(trip.status = :canceledStatus OR trip.departureDate < :today OR (trip.departureDate = :today AND trip.departureTime < :currentTime))')
                ->setParameter('canceledStatus', 'canceled')
                ->setParameter('today', $today)
                ->setParameter('currentTime', $currentTime)
                ->orderBy('trip.departureDate', 'DESC')
                ->addOrderBy('trip.departureTime', 'DESC');

            return $queryBuilder;
        }

        $queryBuilder
            ->orderBy('trip.departureDate', 'DESC')
            ->addOrderBy('trip.departureTime', 'DESC');

        return $queryBuilder;
    }

    private function normalizeHistoryTab(string $tab): string
    {
        return in_array($tab, [self::HISTORY_TAB_ALL, self::HISTORY_TAB_ACTIVE, self::HISTORY_TAB_OLD], true)
            ? $tab
            : self::HISTORY_TAB_ALL;
    }

    private function parseSearchDate(string $search): ?\DateTimeImmutable
    {
        $normalized = trim($search);

        if ($normalized === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $normalized);

            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }
}
