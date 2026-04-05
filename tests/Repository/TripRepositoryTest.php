<?php

namespace App\Tests\Repository;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TripRepositoryTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;
    private TripRepository $tripRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($this->entityManager);
        $this->tripRepository = static::getContainer()->get(TripRepository::class);
    }

    public function testDriverHistoryTabsAndSearch(): void
    {
        $driver = $this->createUser('driver@example.com', 'driver');
        $this->createTrip($driver, 'Paris', 'Lyon', '+2 days', 'planifie');
        $this->createTrip($driver, 'Bordeaux', 'Lille', '-2 days', 'planifie');
        $this->createTrip($driver, 'Nantes', 'Rennes', '+3 days', 'canceled');
        $this->entityManager->flush();

        self::assertCount(3, $this->tripRepository->findDriverHistoryTrips($driver, TripRepository::HISTORY_TAB_ALL));
        self::assertCount(1, $this->tripRepository->findDriverHistoryTrips($driver, TripRepository::HISTORY_TAB_ACTIVE));
        self::assertCount(2, $this->tripRepository->findDriverHistoryTrips($driver, TripRepository::HISTORY_TAB_OLD));
        self::assertCount(1, $this->tripRepository->findDriverHistoryTrips($driver, TripRepository::HISTORY_TAB_ACTIVE, 'Paris'));
        self::assertCount(0, $this->tripRepository->findDriverHistoryTrips($driver, TripRepository::HISTORY_TAB_ACTIVE, 'Bordeaux'));
    }

    public function testPassengerHistoryTabsAndSearch(): void
    {
        $passenger = $this->createUser('passenger@example.com', 'passenger');
        $driverOne = $this->createUser('driver1@example.com', 'driver');
        $driverTwo = $this->createUser('driver2@example.com', 'driver');
        $activeTrip = $this->createTrip($driverOne, 'Paris', 'Lyon', '+2 days', 'planifie');
        $pastTrip = $this->createTrip($driverOne, 'Bordeaux', 'Lille', '-2 days', 'planifie');
        $canceledTrip = $this->createTrip($driverTwo, 'Nantes', 'Rennes', '+3 days', 'canceled');
        $this->createConfirmedBooking($passenger, $activeTrip);
        $this->createConfirmedBooking($passenger, $pastTrip);
        $this->createConfirmedBooking($passenger, $canceledTrip);
        $this->entityManager->flush();

        self::assertCount(3, $this->tripRepository->findPassengerHistoryTrips($passenger, TripRepository::HISTORY_TAB_ALL));
        self::assertCount(1, $this->tripRepository->findPassengerHistoryTrips($passenger, TripRepository::HISTORY_TAB_ACTIVE));
        self::assertCount(2, $this->tripRepository->findPassengerHistoryTrips($passenger, TripRepository::HISTORY_TAB_OLD));
        self::assertCount(1, $this->tripRepository->findPassengerHistoryTrips($passenger, TripRepository::HISTORY_TAB_ACTIVE, 'driver1'));
        self::assertCount(0, $this->tripRepository->findPassengerHistoryTrips($passenger, TripRepository::HISTORY_TAB_ACTIVE, 'driver2'));
        self::assertCount(1, $this->tripRepository->findPassengerHistoryTrips(
            $passenger,
            TripRepository::HISTORY_TAB_ACTIVE,
            $activeTrip->getDepartureDate()?->format('Y-m-d')
        ));
    }

    private function createUser(string $email, string $type): User
    {
        $user = (new User())
            ->setFirstName('Test')
            ->setLastName('User')
            ->setEmail($email)
            ->setPassword('password')
            ->setPhone('0102030405')
            ->setAddress('1 rue de test')
            ->setBirthDate(new \DateTime('1990-01-01'))
            ->setPhoto('photo')
            ->setUsername(str_replace('@example.com', '', $email))
            ->setCreditBalance('100.00')
            ->setRoles(['ROLE_USER'])
            ->setType($type);

        $this->entityManager->persist($user);

        return $user;
    }

    private function createTrip(User $driver, string $departure, string $arrival, string $offset, string $status): Trip
    {
        $departureAt = new \DateTimeImmutable($offset);
        $arrivalAt = $departureAt->modify('+2 hours');

        $trip = (new Trip())
            ->setDriver($driver)
            ->setDepartureLocation($departure)
            ->setArrivalLocation($arrival)
            ->setDepartureDate(\DateTime::createFromImmutable($departureAt))
            ->setDepartureTime(\DateTime::createFromImmutable($departureAt))
            ->setArrivalDate(\DateTime::createFromImmutable($arrivalAt))
            ->setArrivalTime(\DateTime::createFromImmutable($arrivalAt))
            ->setAvailableSeats(3)
            ->setPricePerPerson('12.00')
            ->setStatus($status);

        $this->entityManager->persist($trip);

        return $trip;
    }

    private function createConfirmedBooking(User $user, Trip $trip): Booking
    {
        $booking = (new Booking())
            ->setUser($user)
            ->setTrip($trip)
            ->setConfirmation(true)
            ->setCreditsUsed(12)
            ->setStatus('confirmee');

        $this->entityManager->persist($booking);

        return $booking;
    }
}
