<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Service\BookingCancellationLinkSigner;
use App\Service\TripNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TripNotificationServiceTest extends TestCase
{
    public function testParticipationEmailsContainExpectedMetadata(): void
    {
        $capturedEmails = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedEmails): void {
                $capturedEmails[] = $message;
            });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters = []): string {
                if ($route === 'app_trip_participer_annuler_par_mail') {
                    return sprintf('https://example.test/booking/%d/cancel-from-email?expires=%d', $parameters['id'], $parameters['expires']);
                }

                return sprintf('https://example.test/covoiturages/%d', $parameters['id'] ?? 1);
            });

        $linkSigner = new BookingCancellationLinkSigner($urlGenerator, new UriSigner('test-secret'));

        $service = new TripNotificationService(
            $mailer,
            $urlGenerator,
            $linkSigner,
            new NullLogger(),
            'noreply@example.test',
            'EcoRide'
        );

        $driver = $this->createUser('driver@example.test', 'driver');
        $participant = $this->createUser('participant@example.test', 'participant');
        $trip = $this->createTrip($driver);
        $booking = (new Booking())
            ->setTrip($trip)
            ->setUser($participant)
            ->setConfirmation(true)
            ->setCreditsUsed(12)
            ->setStatus('confirmee');

        $this->setEntityId($booking, 1);
        $service->sendParticipationConfirmed($booking);

        self::assertCount(2, $capturedEmails);
        self::assertSame('Nouvelle participation a votre covoiturage', $capturedEmails[0]->getSubject());
        self::assertSame('Votre participation au covoiturage est confirmee', $capturedEmails[1]->getSubject());
        self::assertSame('https://example.test/covoiturages/1', $capturedEmails[0]->getContext()['tripUrl']);
        self::assertStringContainsString('https://example.test/booking/1/cancel-from-email?', $capturedEmails[1]->getContext()['cancellationUrl']);
        self::assertStringContainsString('expires=', $capturedEmails[1]->getContext()['cancellationUrl']);
        self::assertStringContainsString('_hash=', $capturedEmails[1]->getContext()['cancellationUrl']);
    }

    public function testParticipationCanceledEmailsContainExpectedMetadata(): void
    {
        $capturedEmails = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedEmails): void {
                $capturedEmails[] = $message;
            });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters = []): string {
                if ($route === 'app_trip_participer_annuler_par_mail') {
                    return sprintf('https://example.test/booking/%d/cancel-from-email?expires=%d', $parameters['id'], $parameters['expires']);
                }

                return sprintf('https://example.test/covoiturages/%d', $parameters['id'] ?? 1);
            });

        $linkSigner = new BookingCancellationLinkSigner($urlGenerator, new UriSigner('test-secret'));

        $service = new TripNotificationService(
            $mailer,
            $urlGenerator,
            $linkSigner,
            new NullLogger(),
            'noreply@example.test',
            'EcoRide'
        );

        $driver = $this->createUser('driver@example.test', 'driver');
        $participant = $this->createUser('participant@example.test', 'participant');
        $trip = $this->createTrip($driver);
        $booking = (new Booking())
            ->setTrip($trip)
            ->setUser($participant)
            ->setConfirmation(false)
            ->setCreditsUsed(12)
            ->setStatus('annulee');

        $this->setEntityId($booking, 1);
        $service->sendParticipationCanceled($booking);

        self::assertCount(2, $capturedEmails);
        self::assertSame('Un participant a annule sa participation', $capturedEmails[0]->getSubject());
        self::assertSame('Votre participation a ete annulee', $capturedEmails[1]->getSubject());
        self::assertSame('https://example.test/covoiturages/1', $capturedEmails[0]->getContext()['tripUrl']);
        self::assertSame('participant', $capturedEmails[0]->getContext()['participant']->getUsername());
        self::assertSame('driver', $capturedEmails[1]->getContext()['driver']->getUsername());
    }

    private function createUser(string $email, string $type): User
    {
        return (new User())
            ->setFirstName('Test')
            ->setLastName('User')
            ->setEmail($email)
            ->setPassword('password')
            ->setPhone('0102030405')
            ->setAddress('1 rue de test')
            ->setBirthDate(new \DateTime('1990-01-01'))
            ->setPhoto('photo')
            ->setUsername(str_replace('@example.test', '', $email))
            ->setCreditBalance('100.00')
            ->setRoles(['ROLE_USER'])
            ->setType($type);
    }

    private function createTrip(User $driver): Trip
    {
        $departureAt = new \DateTimeImmutable('+2 days');
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

        $this->setEntityId($trip, 1);

        return $trip;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty($entity, 'id');
        $reflectionProperty->setValue($entity, $id);
    }
}
