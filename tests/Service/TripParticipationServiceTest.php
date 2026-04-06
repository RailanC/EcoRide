<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Service\BookingCancellationLinkSigner;
use App\Service\TripNotificationService;
use App\Service\TripParticipationService;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TripParticipationServiceTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($this->entityManager);
    }

    public function testConfirmParticipationUpdatesBalancesAndSendsEmails(): void
    {
        $driver = $this->createUser('driver@example.test', 'driver', '50.00');
        $passenger = $this->createUser('passenger@example.test', 'passenger', '30.00');
        $trip = $this->createTrip($driver, '+2 days');
        $this->entityManager->flush();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send');

        $service = $this->createParticipationService($mailer);
        $service->confirmParticipation($trip, $passenger);

        $this->entityManager->refresh($driver);
        $this->entityManager->refresh($passenger);
        $this->entityManager->refresh($trip);

        $booking = $this->entityManager->getRepository(Booking::class)->findOneBy([
            'trip' => $trip,
            'user' => $passenger,
            'confirmation' => true,
        ]);

        self::assertInstanceOf(Booking::class, $booking);
        self::assertSame('18', (string) $passenger->getCreditBalance());
        self::assertSame('50', (string) $driver->getCreditBalance());
        self::assertSame(2, $trip->getAvailableSeats());
        self::assertSame(Booking::OUTCOME_PENDING, $booking->getOutcomeStatus());
        self::assertFalse($booking->isPayoutApplied());
        self::assertSame('10.00', $booking->getDriverCreditAmount());
    }

    public function testCancelTripNotifiesConfirmedParticipants(): void
    {
        $driver = $this->createUser('driver@example.test', 'driver', '100.00');
        $passengerOne = $this->createUser('one@example.test', 'passenger', '40.00');
        $passengerTwo = $this->createUser('two@example.test', 'passenger', '40.00');
        $trip = $this->createTrip($driver, '+2 days');
        $this->entityManager->flush();

        $capturedSubjects = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::exactly(6))
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedSubjects): void {
                $capturedSubjects[] = $message->getSubject();
            });

        $service = $this->createParticipationService($mailer);
        $service->confirmParticipation($trip, $passengerOne);
        $service->confirmParticipation($trip, $passengerTwo);

        self::assertTrue($service->cancelTrip($trip, $driver));

        $this->entityManager->refresh($trip);
        self::assertSame('canceled', $trip->getStatus());
        self::assertSame(2, count(array_filter(
            $capturedSubjects,
            static fn (string $subject): bool => $subject === 'Votre covoiturage a ete annule'
        )));
    }

    public function testSignedEmailCancellationCancelsOnlyTargetBooking(): void
    {
        $driver = $this->createUser('driver@example.test', 'driver', '80.00');
        $passenger = $this->createUser('passenger@example.test', 'passenger', '20.00');
        $trip = $this->createTrip($driver, '+2 days');
        $this->entityManager->flush();

        $capturedSubjects = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::exactly(4))
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedSubjects): void {
                $capturedSubjects[] = $message->getSubject();
            });

        $service = $this->createParticipationService($mailer);
        $service->confirmParticipation($trip, $passenger);

        $booking = $this->entityManager->getRepository(Booking::class)->findOneBy([
            'trip' => $trip,
            'user' => $passenger,
            'confirmation' => true,
        ]);

        self::assertInstanceOf(Booking::class, $booking);
        self::assertTrue($service->cancelParticipation($booking));
        self::assertFalse($service->cancelParticipation($booking));

        $this->entityManager->refresh($driver);
        $this->entityManager->refresh($passenger);
        $this->entityManager->refresh($trip);

        self::assertSame('20', (string) $passenger->getCreditBalance());
        self::assertSame('80', (string) $driver->getCreditBalance());
        self::assertSame(3, $trip->getAvailableSeats());
        self::assertContains('Un participant a annule sa participation', $capturedSubjects);
        self::assertContains('Votre participation a ete annulee', $capturedSubjects);
    }

    private function createParticipationService(MailerInterface $mailer): TripParticipationService
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static function (string $route, array $parameters = []): string {
            if ($route === 'app_booking_cancel_from_email') {
                return sprintf('https://example.test/booking/%d/cancel-from-email?expires=%d', $parameters['id'], $parameters['expires']);
            }

            return sprintf('https://example.test/covoiturages/%d', $parameters['id'] ?? 1);
        });

        $linkSigner = new BookingCancellationLinkSigner($urlGenerator, new UriSigner('test-secret'));

        $notificationService = new TripNotificationService(
            $mailer,
            $urlGenerator,
            $linkSigner,
            new NullLogger(),
            'noreply@example.test',
            'EcoRide'
        );

        return new TripParticipationService(
            $this->entityManager,
            static::getContainer()->get(BookingRepository::class),
            $notificationService
        );
    }

    private function createUser(string $email, string $type, string $credits): User
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
            ->setUsername(str_replace('@example.test', '', $email))
            ->setCreditBalance($credits)
            ->setRoles(['ROLE_USER'])
            ->setType($type);

        $this->entityManager->persist($user);

        return $user;
    }

    private function createTrip(User $driver, string $offset): Trip
    {
        $departureAt = new \DateTimeImmutable($offset);
        $arrivalAt = $departureAt->modify('+2 hours');

        $trip = (new Trip())
            ->setDriver($driver)
            ->setDepartureLocation('Paris')
            ->setArrivalLocation('Lyon')
            ->setDepartureDate(\DateTime::createFromImmutable($departureAt))
            ->setDepartureTime(\DateTime::createFromImmutable($departureAt))
            ->setArrivalDate(\DateTime::createFromImmutable($arrivalAt))
            ->setArrivalTime(\DateTime::createFromImmutable($arrivalAt))
            ->setAvailableSeats(3)
            ->setPricePerPerson('12.00')
            ->setStatus('planifie');

        $this->entityManager->persist($trip);

        return $trip;
    }
}
