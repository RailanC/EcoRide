<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\TripParticipationException;
use App\Repository\BookingRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TripParticipationService
{
    private const PLATFORM_FEE_CENTS = 200;
    private const TRIP_STATUS_CANCELED = 'canceled';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    public function confirmParticipation(Trip $trip, User $passenger): void
    {
        $tripId = $trip->getId();
        $passengerId = $passenger->getId();

        if ($tripId === null || $passengerId === null) {
            throw new TripParticipationException('Impossible de confirmer cette participation.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedTrip = $this->entityManager->find(Trip::class, $tripId, LockMode::PESSIMISTIC_WRITE);
            $lockedPassenger = $this->entityManager->find(User::class, $passengerId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedPassenger instanceof User) {
                throw new TripParticipationException('Le trajet ou le passager est introuvable.');
            }

            $driver = $lockedTrip->getDriver();

            if (!$driver instanceof User || $driver->getId() === null) {
                throw new TripParticipationException('Ce trajet n a pas de conducteur valide.');
            }

            if ($driver->getId() === $lockedPassenger->getId()) {
                throw new TripParticipationException('Vous ne pouvez pas participer a votre propre trajet.');
            }

            $lockedDriver = $this->entityManager->find(User::class, $driver->getId(), LockMode::PESSIMISTIC_WRITE);

            if (!$lockedDriver instanceof User) {
                throw new TripParticipationException('Le conducteur de ce trajet est introuvable.');
            }

            if ($lockedTrip->getStatus() === self::TRIP_STATUS_CANCELED) {
                throw new TripParticipationException('Ce trajet est deja annule.');
            }

            if (($lockedTrip->getAvailableSeats() ?? 0) <= 0) {
                throw new TripParticipationException('Il n y a plus de places disponibles.');
            }

            $existingBooking = $this->bookingRepository->findOneBy([
                'user' => $lockedPassenger,
                'trip' => $lockedTrip,
            ]);

            if ($existingBooking instanceof Booking) {
                throw new TripParticipationException('Vous participez deja a ce trajet.');
            }

            $tripPriceCents = $this->creditsToCents((string) $lockedTrip->getPricePerPerson());
            $passengerBalanceCents = $this->creditsToCents((string) $lockedPassenger->getCreditBalance());

            if ($passengerBalanceCents < $tripPriceCents) {
                throw new TripParticipationException('Vous n avez pas assez de credits.');
            }

            $driverBalanceCents = $this->creditsToCents((string) $lockedDriver->getCreditBalance());
            $driverEarningsCents = $this->getDriverEarningsCents($tripPriceCents);

            $booking = new Booking();
            $booking->setUser($lockedPassenger);
            $booking->setTrip($lockedTrip);
            $booking->setConfirmation(true);
            $booking->setCreditsUsed((int) round($tripPriceCents / 100));
            $booking->setStatus('confirmee');

            $lockedPassenger->setCreditBalance($this->centsToCredits($passengerBalanceCents - $tripPriceCents));
            $lockedDriver->setCreditBalance($this->centsToCredits($driverBalanceCents + $driverEarningsCents));
            $lockedTrip->setAvailableSeats(((int) $lockedTrip->getAvailableSeats()) - 1);

            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible de confirmer cette participation pour le moment.', 0, $throwable);
        }
    }

    public function cancelTrip(Trip $trip, User $currentUser): bool
    {
        $tripId = $trip->getId();
        $currentUserId = $currentUser->getId();

        if ($tripId === null || $currentUserId === null) {
            throw new TripParticipationException('Impossible d annuler ce trajet.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedTrip = $this->entityManager->find(Trip::class, $tripId, LockMode::PESSIMISTIC_WRITE);
            $lockedCurrentUser = $this->entityManager->find(User::class, $currentUserId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedCurrentUser instanceof User) {
                throw new TripParticipationException('Le trajet ou le conducteur est introuvable.');
            }

            $driver = $lockedTrip->getDriver();

            if (!$driver instanceof User || $driver->getId() === null || $driver->getId() !== $lockedCurrentUser->getId()) {
                throw new TripParticipationException('Vous ne pouvez pas annuler ce trajet.');
            }

            if ($lockedTrip->getStatus() === self::TRIP_STATUS_CANCELED) {
                $this->entityManager->commit();

                return false;
            }

            $lockedDriver = $this->entityManager->find(User::class, $driver->getId(), LockMode::PESSIMISTIC_WRITE);

            if (!$lockedDriver instanceof User) {
                throw new TripParticipationException('Le conducteur de ce trajet est introuvable.');
            }

            $confirmedBookings = array_values(array_filter(
                $lockedTrip->getBookings()->toArray(),
                static fn (Booking $booking): bool => $booking->isConfirmation() === true && $booking->getUser() !== null
            ));

            $tripPriceCents = $this->creditsToCents((string) $lockedTrip->getPricePerPerson());
            $driverRefundPerPassengerCents = $this->getDriverEarningsCents($tripPriceCents);
            $driverBalanceCents = $this->creditsToCents((string) $lockedDriver->getCreditBalance());
            $totalDriverDebitCents = $driverRefundPerPassengerCents * count($confirmedBookings);

            if ($driverBalanceCents < $totalDriverDebitCents) {
                throw new TripParticipationException('Le conducteur ne dispose pas de suffisamment de credits pour annuler ce trajet.');
            }

            foreach ($confirmedBookings as $booking) {
                $passenger = $booking->getUser();

                if (!$passenger instanceof User || $passenger->getId() === null) {
                    continue;
                }

                $lockedPassenger = $this->entityManager->find(User::class, $passenger->getId(), LockMode::PESSIMISTIC_WRITE);

                if (!$lockedPassenger instanceof User) {
                    throw new TripParticipationException('Impossible de rembourser un passager de ce trajet.');
                }

                $passengerBalanceCents = $this->creditsToCents((string) $lockedPassenger->getCreditBalance());
                $lockedPassenger->setCreditBalance($this->centsToCredits($passengerBalanceCents + $tripPriceCents));
                $booking->setConfirmation(false);
                $booking->setStatus('remboursee');
            }

            $lockedDriver->setCreditBalance($this->centsToCredits($driverBalanceCents - $totalDriverDebitCents));
            $lockedTrip->setAvailableSeats(((int) $lockedTrip->getAvailableSeats()) + count($confirmedBookings));
            $lockedTrip->setStatus(self::TRIP_STATUS_CANCELED);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return true;
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible d annuler ce trajet pour le moment.', 0, $throwable);
        }
    }

    public function getPlatformFeeCredits(): string
    {
        return $this->centsToCredits(self::PLATFORM_FEE_CENTS);
    }

    public function getDriverEarnings(string $tripPriceCredits): string
    {
        return $this->centsToCredits($this->getDriverEarningsCents($this->creditsToCents($tripPriceCredits)));
    }

    private function getDriverEarningsCents(int $tripPriceCents): int
    {
        return max(0, $tripPriceCents - self::PLATFORM_FEE_CENTS);
    }

    private function creditsToCents(string $credits): int
    {
        $normalized = str_replace(',', '.', trim($credits));

        if ($normalized === '') {
            return 0;
        }

        $sign = 1;

        if (str_starts_with($normalized, '-')) {
            $sign = -1;
            $normalized = substr($normalized, 1);
        }

        [$wholePart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '0');
        $whole = (int) $wholePart;
        $fraction = (int) substr(str_pad($fractionPart, 2, '0'), 0, 2);

        return $sign * (($whole * 100) + $fraction);
    }

    private function centsToCredits(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
