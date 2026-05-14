<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TripNotificationService
{
    private const NOTIFICATION_JOIN_DRIVER = 'join_driver';
    private const NOTIFICATION_JOIN_PARTICIPANT = 'join_participant';
    private const NOTIFICATION_PARTICIPATION_CANCELED_DRIVER = 'participation_canceled_driver';
    private const NOTIFICATION_PARTICIPATION_CANCELED_PARTICIPANT = 'participation_canceled_participant';
    private const NOTIFICATION_TRIP_CANCELED_PARTICIPANT = 'trip_canceled_participant';
    private const NOTIFICATION_TRIP_ARRIVED_PARTICIPANT = 'trip_arrived_participant';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BookingCancellationLinkSigner $bookingCancellationLinkSigner,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function sendParticipationConfirmed(Booking $booking): void
    {
        $trip = $booking->getTrip();
        $participant = $booking->getUser();
        $driver = $trip?->getDriver();

        if (!$trip instanceof Trip || !$participant instanceof User || !$driver instanceof User) {
            return;
        }

        $tripUrl = $this->generateTripUrl($trip);
        $cancellationUrl = $this->bookingCancellationLinkSigner->generateSignedCancellationUrl($booking);

        $this->send(
            self::NOTIFICATION_JOIN_DRIVER,
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address((string) $driver->getEmail(), (string) $driver->getUsername()))
                ->subject('Nouvelle participation a votre covoiturage')
                ->htmlTemplate('emails/trip_participation_driver.html.twig')
                ->context([
                    'trip' => $trip,
                    'driver' => $driver,
                    'participant' => $participant,
                    'tripUrl' => $tripUrl,
                ])
        );

        $this->send(
            self::NOTIFICATION_JOIN_PARTICIPANT,
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address((string) $participant->getEmail(), (string) $participant->getUsername()))
                ->subject('Votre participation au covoiturage est confirmee')
                ->htmlTemplate('emails/trip_participation_participant.html.twig')
                ->context([
                    'trip' => $trip,
                    'driver' => $driver,
                    'participant' => $participant,
                    'tripUrl' => $tripUrl,
                    'cancellationUrl' => $cancellationUrl,
                ])
        );
    }

    public function sendParticipationCanceled(Booking $booking): void
    {
        $trip = $booking->getTrip();
        $participant = $booking->getUser();
        $driver = $trip?->getDriver();

        if (!$trip instanceof Trip || !$participant instanceof User || !$driver instanceof User) {
            return;
        }

        $tripUrl = $this->generateTripUrl($trip);

        $this->send(
            self::NOTIFICATION_PARTICIPATION_CANCELED_DRIVER,
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address((string) $driver->getEmail(), (string) $driver->getUsername()))
                ->subject('Un participant a annule sa participation')
                ->htmlTemplate('emails/trip_participation_canceled_driver.html.twig')
                ->context([
                    'trip' => $trip,
                    'driver' => $driver,
                    'participant' => $participant,
                    'tripUrl' => $tripUrl,
                ])
        );

        $this->send(
            self::NOTIFICATION_PARTICIPATION_CANCELED_PARTICIPANT,
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address((string) $participant->getEmail(), (string) $participant->getUsername()))
                ->subject('Votre participation a ete annulee')
                ->htmlTemplate('emails/trip_participation_canceled_participant.html.twig')
                ->context([
                    'trip' => $trip,
                    'driver' => $driver,
                    'participant' => $participant,
                    'tripUrl' => $tripUrl,
                ])
        );
    }

    /**
     * @param array<Booking> $bookings
     */
    public function sendTripCanceled(Trip $trip, array $bookings): void
    {
        $driver = $trip->getDriver();

        if (!$driver instanceof User) {
            return;
        }

        $tripUrl = $this->generateTripUrl($trip);

        foreach ($bookings as $booking) {
            $participant = $booking->getUser();

            if (!$participant instanceof User) {
                continue;
            }

            $this->send(
                self::NOTIFICATION_TRIP_CANCELED_PARTICIPANT,
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to(new Address((string) $participant->getEmail(), (string) $participant->getUsername()))
                    ->subject('Votre covoiturage a ete annule')
                    ->htmlTemplate('emails/trip_canceled_participant.html.twig')
                    ->context([
                        'trip' => $trip,
                        'driver' => $driver,
                        'participant' => $participant,
                        'tripUrl' => $tripUrl,
                    ])
            );
        }
    }

    /**
     * @param array<Booking> $bookings
     */
    public function sendTripArrivedForValidation(Trip $trip, array $bookings): void
    {
        $driver = $trip->getDriver();

        if (!$driver instanceof User) {
            return;
        }

        $tripUrl = $this->generateTripUrl($trip);
        $dashboardUrl = $this->urlGenerator->generate('app_profile_trips', [], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($bookings as $booking) {
            $participant = $booking->getUser();

            if (!$participant instanceof User) {
                continue;
            }

            $this->send(
                self::NOTIFICATION_TRIP_ARRIVED_PARTICIPANT,
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to(new Address((string) $participant->getEmail(), (string) $participant->getUsername()))
                    ->subject('Confirmez le deroulement de votre trajet EcoRide')
                    ->htmlTemplate('emails/trip_arrived_participant.html.twig')
                    ->context([
                        'trip' => $trip,
                        'driver' => $driver,
                        'participant' => $participant,
                        'tripUrl' => $tripUrl,
                        'dashboardUrl' => $dashboardUrl,
                    ])
            );
        }
    }

    private function generateTripUrl(Trip $trip): string
    {
        return $this->urlGenerator->generate('app_trip_show', [
            'id' => $trip->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function send(string $notificationType, TemplatedEmail $email): void
    {
        $recipientAddresses = array_map(
            static fn (Address $address): string => $address->getAddress(),
            $email->getTo()
        );

        $this->logger->info('Queueing trip notification email.', [
            'notification_type' => $notificationType,
            'subject' => $email->getSubject(),
            'recipients' => $recipientAddresses,
        ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Trip notification email handed to mailer transport.', [
                'notification_type' => $notificationType,
                'subject' => $email->getSubject(),
                'recipients' => $recipientAddresses,
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->error('Unable to send trip notification email.', [
                'notification_type' => $notificationType,
                'exception' => $throwable,
                'subject' => $email->getSubject(),
                'recipients' => $recipientAddresses,
            ]);
        }
    }
}
