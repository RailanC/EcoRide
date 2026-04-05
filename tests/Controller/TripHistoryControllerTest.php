<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Brand;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TripHistoryControllerTest extends WebTestCase
{
    use ResetsDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($this->entityManager);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/history');

        self::assertResponseRedirects('/login');
    }

    public function testDriverSeesTabsAndOwnTripsOnly(): void
    {
        $driver = $this->createUser('driver@example.com', 'driver');
        $otherDriver = $this->createUser('other@example.com', 'driver');
        $trip = $this->createTrip($driver, 'Paris', 'Lyon', '+2 days');
        $this->createTrip($otherDriver, 'Marseille', 'Nice', '+2 days');
        $this->entityManager->flush();

        $this->client->loginUser($driver);
        $crawler = $this->client->request('GET', '/history');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('a:contains("Tous les trajets crees")')->count());
        self::assertSelectorTextContains('body', 'Paris');
        self::assertSelectorTextNotContains('body', 'Marseille');

        $link = $crawler->selectLink('Voir le trajet')->link();
        $this->client->click($link);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Paris');
        self::assertSelectorTextContains('body', 'Lyon');
    }

    public function testPassengerSeesBookedTripsInPassengerMode(): void
    {
        $driver = $this->createUser('driver@example.com', 'driver');
        $passenger = $this->createUser('passenger@example.com', 'passenger');
        $trip = $this->createTrip($driver, 'Nantes', 'Bordeaux', '+2 days');
        $this->createConfirmedBooking($passenger, $trip);
        $this->entityManager->flush();

        $this->client->loginUser($passenger);
        $crawler = $this->client->request('GET', '/history');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Historique des trajets');
        self::assertSelectorTextContains('body', 'Tous les trajets reserves');
        self::assertSelectorTextContains('body', 'Nantes');
        self::assertSelectorTextContains('body', 'Bordeaux');
        self::assertSelectorTextContains('body', 'Conducteur: driver');

        $link = $crawler->selectLink('Voir le trajet')->link();
        $this->client->click($link);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Nantes');
        self::assertSelectorTextContains('body', 'Bordeaux');
    }

    public function testBothUserCanSwitchBetweenDriverAndPassengerHistoryModes(): void
    {
        $bothUser = $this->createUser('both@example.com', 'both');
        $otherDriver = $this->createUser('other@example.com', 'driver');
        $driverTrip = $this->createTrip($bothUser, 'Paris', 'Lyon', '+2 days');
        $passengerTrip = $this->createTrip($otherDriver, 'Lille', 'Rouen', '+3 days');
        $this->createConfirmedBooking($bothUser, $passengerTrip);
        $this->entityManager->flush();

        $this->client->loginUser($bothUser);
        $this->client->request('GET', '/history?mode=driver');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Historique conducteur');
        self::assertSelectorTextContains('body', 'Paris');
        self::assertSelectorTextNotContains('body', 'Rouen');

        $this->client->request('GET', '/history?mode=passenger');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Historique passager');
        self::assertSelectorTextContains('body', 'Lille');
        self::assertSelectorTextContains('body', 'Rouen');
        self::assertSelectorTextNotContains('body', 'Paris');
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

    private function createTrip(User $driver, string $departure, string $arrival, string $offset): Trip
    {
        $brand = (new Brand())->setLabel('Renault');
        $vehicle = (new Vehicle())
            ->setBrand($brand)
            ->setOwner($driver)
            ->setModel('Zoe')
            ->setRegistrationNumber(uniqid('AA-', true))
            ->setEnergyType('Electrique')
            ->setColor('Bleu')
            ->setFirstRegistrationDate(new \DateTime('2020-01-01'))
            ->setPlaces(4)
            ->setPreferences(null);

        $departureAt = new \DateTimeImmutable($offset);
        $arrivalAt = $departureAt->modify('+2 hours');

        $trip = (new Trip())
            ->setDriver($driver)
            ->setVehicle($vehicle)
            ->setDepartureLocation($departure)
            ->setArrivalLocation($arrival)
            ->setDepartureDate(\DateTime::createFromImmutable($departureAt))
            ->setDepartureTime(\DateTime::createFromImmutable($departureAt))
            ->setArrivalDate(\DateTime::createFromImmutable($arrivalAt))
            ->setArrivalTime(\DateTime::createFromImmutable($arrivalAt))
            ->setAvailableSeats(3)
            ->setPricePerPerson('12.00')
            ->setStatus('planifie');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($vehicle);
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
