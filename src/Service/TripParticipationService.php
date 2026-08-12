<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\TripIssue;
use App\Entity\User;
use App\Exception\TripParticipationException;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Repository\TripIssueRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TripParticipationService
{
    private const PLATFORM_FEE_CENTS = 200;

    private readonly ReviewRepository $reviewRepository;
    private readonly TripIssueRepository $tripIssueRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly TripNotificationService $tripNotificationService,
        ?ReviewRepository $reviewRepository = null,
        ?TripIssueRepository $tripIssueRepository = null,
    ) {
        $this->reviewRepository = $reviewRepository ?? $entityManager->getRepository(Review::class);
        $this->tripIssueRepository = $tripIssueRepository ?? $entityManager->getRepository(TripIssue::class);
    }

    public function confirmParticipation(Trip $trip, User $passenger): void
    {
        $tripId = $trip->getId();
        $passengerId = $passenger->getId();
        $bookingToNotify = null;

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
                throw new TripParticipationException('Ce trajet n\'a pas de conducteur valide.');
            }

            if ($driver->getId() === $lockedPassenger->getId()) {
                throw new TripParticipationException('Vous ne pouvez pas participer à votre propre trajet.');
            }

            if (!$this->isPlannedTripStatus($lockedTrip->getStatus())) {
                throw new TripParticipationException('Ce trajet n\'accepte plus de nouvelles participations.');
            }

            if (($lockedTrip->getAvailableSeats() ?? 0) <= 0) {
                throw new TripParticipationException('Il n\'y a plus de places disponibles.');
            }

            $existingBooking = $this->bookingRepository->findOneBy([
                'user' => $lockedPassenger,
                'trip' => $lockedTrip,
                'confirmation' => true,
            ]);

            if ($existingBooking instanceof Booking) {
                throw new TripParticipationException('Vous participez déjà à ce trajet.');
            }

            $tripPriceCents = $this->creditsToCents((string) $lockedTrip->getPricePerPerson());
            $passengerBalanceCents = $this->creditsToCents((string) $lockedPassenger->getCreditBalance());

            if ($passengerBalanceCents < $tripPriceCents) {
                throw new TripParticipationException('Vous n\'avez pas assez de crédits.');
            }

            $booking = new Booking();
            $booking->setUser($lockedPassenger);
            $booking->setTrip($lockedTrip);
            $booking->setConfirmation(true);
            $booking->setCreditsUsed((int) round($tripPriceCents / 100));
            $booking->setStatus(Booking::STATUS_CONFIRMED);
            $booking->setOutcomeStatus(Booking::OUTCOME_PENDING);
            $booking->setRespondedAt(null);
            $booking->setPayoutApplied(false);
            $booking->setDriverCreditAmount($this->centsToCredits($this->getDriverEarningsCents($tripPriceCents)));

            $lockedPassenger->setCreditBalance($this->centsToCredits($passengerBalanceCents - $tripPriceCents));
            $lockedTrip->setAvailableSeats(((int) $lockedTrip->getAvailableSeats()) - 1);

            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            $this->entityManager->commit();
            $bookingToNotify = $booking;
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible de confirmer cette participation pour le moment.', 0, $throwable);
        }

        if ($bookingToNotify instanceof Booking) {
            $this->tripNotificationService->sendParticipationConfirmed($bookingToNotify);
        }
    }

    public function startTrip(Trip $trip, User $currentUser): void
    {
        $tripId = $trip->getId();
        $currentUserId = $currentUser->getId();

        if ($tripId === null || $currentUserId === null) {
            throw new TripParticipationException('Le trajet ou le conducteur est introuvable.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedTrip = $this->entityManager->find(Trip::class, $tripId, LockMode::PESSIMISTIC_WRITE);
            $lockedCurrentUser = $this->entityManager->find(User::class, $currentUserId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedCurrentUser instanceof User) {
                throw new TripParticipationException('Le trajet ou le conducteur est introuvable.');
            }

            $this->assertDriverOwnsTrip($lockedTrip, $lockedCurrentUser);

            if ($lockedTrip->getStatus() !== Trip::STATUS_PLANNED) {
                throw new TripParticipationException('Ce trajet ne peut pas être démarré.');
            }

            $lockedTrip->setStatus(Trip::STATUS_IN_PROGRESS);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible de démarrer ce trajet pour le moment.', 0, $throwable);
        }
    }

    public function markTripArrived(Trip $trip, User $currentUser): void
    {
        $tripId = $trip->getId();
        $currentUserId = $currentUser->getId();
        $bookingsToNotify = [];

        if ($tripId === null || $currentUserId === null) {
            throw new TripParticipationException('Impossible de terminer ce trajet.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedTrip = $this->entityManager->find(Trip::class, $tripId, LockMode::PESSIMISTIC_WRITE);
            $lockedCurrentUser = $this->entityManager->find(User::class, $currentUserId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedCurrentUser instanceof User) {
                throw new TripParticipationException('Le trajet ou le conducteur est introuvable.');
            }

            $this->assertDriverOwnsTrip($lockedTrip, $lockedCurrentUser);

            if ($lockedTrip->getStatus() !== Trip::STATUS_IN_PROGRESS) {
                throw new TripParticipationException('Seul un trajet en cours peut être marqué comme arrivé.');
            }

            $confirmedBookings = $this->bookingRepository->findConfirmedParticipantsForTrip($lockedTrip);

            if ($confirmedBookings === []) {
                $lockedTrip->setStatus(Trip::STATUS_COMPLETED);
            } else {
                $lockedTrip->setStatus(Trip::STATUS_ARRIVED);
                $bookingsToNotify = $confirmedBookings;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible de terminer ce trajet pour le moment.', 0, $throwable);
        }

        if ($bookingsToNotify !== []) {
            $this->tripNotificationService->sendTripArrivedForValidation($trip, $bookingsToNotify);
        }
    }

    public function submitTripOutcome(
        Trip $trip,
        User $participant,
        int $rating,
        string $outcome,
        string $comment = ''
    ): void
    {
        $tripId = $trip->getId();
        $participantId = $participant->getId();

        if ($tripId === null || $participantId === null) {
            throw new TripParticipationException('Impossible de valider ce trajet.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedTrip = $this->entityManager->find(Trip::class, $tripId, LockMode::PESSIMISTIC_WRITE);
            $lockedParticipant = $this->entityManager->find(User::class, $participantId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedParticipant instanceof User) {
                throw new TripParticipationException('Le trajet ou le participant est introuvable.');
            }

            $booking = $this->bookingRepository->findParticipantBookingForTrip($lockedParticipant, $lockedTrip);

            if (!$booking instanceof Booking) {
                throw new TripParticipationException('Vous ne participez pas a ce trajet.');
            }

            if (!in_array($lockedTrip->getStatus(), [Trip::STATUS_ARRIVED, Trip::STATUS_DISPUTED], true)) {
                throw new TripParticipationException('Ce trajet ne peut pas encore être validé.');
            }

            if ($booking->getRespondedAt() !== null) {
                throw new TripParticipationException('Vous avez déjà validé ce trajet.');
            }

            $driver = $lockedTrip->getDriver();

            if (!$driver instanceof User) {
                throw new TripParticipationException('Le conducteur de ce trajet est introuvable.');
            }

            $reviewComment = trim($comment);

            if ($outcome === 'good') {
                $booking->setOutcomeStatus(Booking::OUTCOME_CONFIRMED_GOOD);
                $booking->setRespondedAt(new \DateTime());
                $this->createReviewFromValidation($lockedTrip, $lockedParticipant, $driver, $rating, $reviewComment);

                if ($this->allParticipantsConfirmedGood($lockedTrip)) {
                    $this->applyEligibleDriverCredits($lockedTrip);
                    $lockedTrip->setStatus(Trip::STATUS_COMPLETED);
                } elseif ($this->allParticipantsResponded($lockedTrip) && !$this->tripHasOpenIssues($lockedTrip)) {
                    $this->applyEligibleDriverCredits($lockedTrip);
                    $lockedTrip->setStatus(Trip::STATUS_COMPLETED);
                }
            } elseif ($outcome === 'bad') {
                if (trim($comment) === '') {
                    throw new TripParticipationException('Veuillez décrire le problème rencontré.');
                }

                $booking->setOutcomeStatus(Booking::OUTCOME_REPORTED_PROBLEM);
                $booking->setRespondedAt(new \DateTime());
                $this->createReviewFromValidation($lockedTrip, $lockedParticipant, $driver, $rating, $reviewComment);

                $issue = new TripIssue();
                $issue->setTrip($lockedTrip);
                $issue->setBooking($booking);
                $issue->setParticipant($lockedParticipant);
                $issue->setDriver($driver);
                $issue->setComment($reviewComment);
                $issue->setStatus(TripIssue::STATUS_OPEN);
                $issue->setCreatedAt(new \DateTime());
                $this->entityManager->persist($issue);

                $lockedTrip->setStatus(Trip::STATUS_DISPUTED);
            } else {
                throw new TripParticipationException('Le résultat du trajet est invalide.');
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible de valider ce trajet pour le moment.', 0, $throwable);
        }
    }

    public function submitReview(Trip $trip, User $author, int $rating, string $comment): void
    {
        $booking = $this->bookingRepository->findParticipantBookingForTrip($author, $trip);

        if (!$booking instanceof Booking) {
            throw new TripParticipationException('Vous ne pouvez pas laisser un avis pour ce trajet.');
        }

        $driver = $trip->getDriver();

        if (!$driver instanceof User || $trip->getId() === null) {
            throw new TripParticipationException('Le conducteur de ce trajet est introuvable.');
        }

        if ($booking->getOutcomeStatus() !== Booking::OUTCOME_CONFIRMED_GOOD) {
            throw new TripParticipationException('Vous devez confirmer que le trajet s\'est bien passé avant de laisser un avis.');
        }

        if (!$this->isReviewableTripStatus($trip->getStatus())) {
            throw new TripParticipationException('Vous ne pouvez pas encore laisser un avis pour ce trajet.');
        }

        if ($this->reviewRepository->findOneByTripAuthorAndDriver($trip->getId(), $author, $driver) instanceof Review) {
            throw new TripParticipationException('Vous avez déjà laissé un avis pour ce trajet.');
        }

        $review = new Review();
        $review->setTrip($trip);
        $review->setAuthor($author);
        $review->setUser($driver);
        $review->setRating($rating);
        $review->setComment(trim($comment));
        $review->setStatus(Review::STATUS_PENDING);

        $this->entityManager->persist($review);
        $this->entityManager->flush();
    }

    public function moderateReview(Review $review, User $moderator, string $status, ?string $note = null): void
    {
        if (!in_array($status, [Review::STATUS_APPROVED, Review::STATUS_REJECTED], true)) {
            throw new TripParticipationException('Le statut de modération de l\'avis est invalide.');
        }

        $review->setStatus($status);
        $review->setModeratedBy($moderator);
        $review->setModeratedAt(new \DateTime());
        $review->setModerationNote($note !== null && trim($note) !== '' ? trim($note) : null);
        $this->entityManager->flush();
    }

    public function resolveIssue(TripIssue $issue, User $resolver, string $resolution, string $resolutionNote): void
    {
        $issueId = $issue->getId();

        if ($issueId === null) {
            throw new TripParticipationException('Impossible de resoudre ce signalement.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedIssue = $this->entityManager->find(TripIssue::class, $issueId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedIssue instanceof TripIssue) {
                throw new TripParticipationException('Le signalement est introuvable.');
            }

            if ($lockedIssue->getStatus() !== TripIssue::STATUS_OPEN) {
                throw new TripParticipationException('Ce signalement a déjà été traité.');
            }

            $booking = $lockedIssue->getBooking();
            $trip = $lockedIssue->getTrip();

            if (!$booking instanceof Booking || !$trip instanceof Trip || $trip->getId() === null) {
                throw new TripParticipationException('Le signalement n\'est plus rattaché à un trajet valide.');
            }

            $lockedTrip = $this->entityManager->find(Trip::class, $trip->getId(), LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip) {
                throw new TripParticipationException('Le trajet signale est introuvable.');
            }

            if (!in_array($resolution, [TripIssue::STATUS_RESOLVED_FOR_DRIVER, TripIssue::STATUS_RESOLVED_AGAINST_DRIVER], true)) {
                throw new TripParticipationException('La résolution choisie est invalide.');
            }

            $lockedIssue->setStatus($resolution);
            $lockedIssue->setResolutionNote(trim($resolutionNote));
            $lockedIssue->setResolvedBy($resolver);
            $lockedIssue->setResolvedAt(new \DateTime());

            if ($resolution === TripIssue::STATUS_RESOLVED_FOR_DRIVER) {
                $booking->setOutcomeStatus(Booking::OUTCOME_CONFIRMED_GOOD);
            }

            if (!$this->tripHasOpenIssues($lockedTrip) && $this->allParticipantsResponded($lockedTrip)) {
                $this->applyEligibleDriverCredits($lockedTrip);
                $lockedTrip->setStatus(Trip::STATUS_COMPLETED);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible de résoudre ce signalement pour le moment.', 0, $throwable);
        }
    }

    public function cancelTrip(Trip $trip, User $currentUser): bool
    {
        $tripId = $trip->getId();
        $currentUserId = $currentUser->getId();
        $bookingsToNotify = [];

        if ($tripId === null || $currentUserId === null) {
            throw new TripParticipationException('Impossible d\'annuler ce trajet.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedTrip = $this->entityManager->find(Trip::class, $tripId, LockMode::PESSIMISTIC_WRITE);
            $lockedCurrentUser = $this->entityManager->find(User::class, $currentUserId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedCurrentUser instanceof User) {
                throw new TripParticipationException('Le trajet ou le conducteur est introuvable.');
            }

            $this->assertDriverOwnsTrip($lockedTrip, $lockedCurrentUser);

            if ($lockedTrip->getStatus() === Trip::STATUS_CANCELED) {
                $this->entityManager->commit();

                return false;
            }

            if (!$this->isPlannedTripStatus($lockedTrip->getStatus())) {
                throw new TripParticipationException('Seul un trajet planifié peut être annulé.');
            }

            $confirmedBookings = $this->bookingRepository->findConfirmedParticipantsForTrip($lockedTrip);
            $tripPriceCents = $this->creditsToCents((string) $lockedTrip->getPricePerPerson());

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
                $booking->setStatus(Booking::STATUS_REFUNDED);
                $bookingsToNotify[] = $booking;
            }

            $lockedTrip->setAvailableSeats(((int) $lockedTrip->getAvailableSeats()) + count($confirmedBookings));
            $lockedTrip->setStatus(Trip::STATUS_CANCELED);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible d\'annuler ce trajet pour le moment.', 0, $throwable);
        }

        if ($bookingsToNotify !== []) {
            $this->tripNotificationService->sendTripCanceled($trip, $bookingsToNotify);
        }

        return true;
    }

    public function cancelParticipation(Booking $booking): bool
    {
        $bookingId = $booking->getId();
        $bookingToNotify = null;

        if ($bookingId === null) {
            throw new TripParticipationException('Impossible d\'annuler cette participation.');
        }

        $this->entityManager->beginTransaction();

        try {
            $lockedBooking = $this->entityManager->find(Booking::class, $bookingId, LockMode::PESSIMISTIC_WRITE);

            if (!$lockedBooking instanceof Booking) {
                throw new TripParticipationException('La participation demandée est introuvable.');
            }

            if ($lockedBooking->isConfirmation() !== true) {
                $this->entityManager->commit();

                return false;
            }

            $trip = $lockedBooking->getTrip();
            $passenger = $lockedBooking->getUser();

            if (!$trip instanceof Trip || !$passenger instanceof User || $trip->getId() === null || $passenger->getId() === null) {
                throw new TripParticipationException('Cette participation est invalide.');
            }

            $lockedTrip = $this->entityManager->find(Trip::class, $trip->getId(), LockMode::PESSIMISTIC_WRITE);
            $lockedPassenger = $this->entityManager->find(User::class, $passenger->getId(), LockMode::PESSIMISTIC_WRITE);

            if (!$lockedTrip instanceof Trip || !$lockedPassenger instanceof User) {
                throw new TripParticipationException('Impossible de charger les données de cette participation.');
            }

            if (!$this->isPlannedTripStatus($lockedTrip->getStatus())) {
                throw new TripParticipationException('Ce trajet ne peut plus être annulé.');
            }

            $tripPriceCents = $this->creditsToCents((string) $lockedTrip->getPricePerPerson());
            $passengerBalanceCents = $this->creditsToCents((string) $lockedPassenger->getCreditBalance());
            $lockedPassenger->setCreditBalance($this->centsToCredits($passengerBalanceCents + $tripPriceCents));
            $lockedTrip->setAvailableSeats(((int) $lockedTrip->getAvailableSeats()) + 1);
            $lockedBooking->setConfirmation(false);
            $lockedBooking->setStatus(Booking::STATUS_CANCELED);

            $this->entityManager->flush();
            $this->entityManager->commit();
            $bookingToNotify = $lockedBooking;
        } catch (\Throwable $throwable) {
            $this->entityManager->rollback();

            if ($throwable instanceof TripParticipationException) {
                throw $throwable;
            }

            throw new TripParticipationException('Impossible d annuler cette participation pour le moment.', 0, $throwable);
        }

        if ($bookingToNotify instanceof Booking) {
            $this->tripNotificationService->sendParticipationCanceled($bookingToNotify);
        }

        return true;
    }

    public function getPlatformFeeCredits(): string
    {
        return $this->centsToCredits(self::PLATFORM_FEE_CENTS);
    }

    public function getDriverEarnings(string $tripPriceCredits): string
    {
        return $this->centsToCredits($this->getDriverEarningsCents($this->creditsToCents($tripPriceCredits)));
    }

    private function assertDriverOwnsTrip(Trip $trip, User $currentUser): void
    {
        $driver = $trip->getDriver();

        if (!$driver instanceof User || $driver->getId() !== $currentUser->getId()) {
            throw new TripParticipationException('Vous ne pouvez pas modifier ce trajet.');
        }
    }

    private function allParticipantsConfirmedGood(Trip $trip): bool
    {
        $bookings = $this->bookingRepository->findConfirmedParticipantsForTrip($trip);

        if ($bookings === []) {
            return true;
        }

        foreach ($bookings as $booking) {
            if ($booking->getOutcomeStatus() !== Booking::OUTCOME_CONFIRMED_GOOD) {
                return false;
            }
        }

        return true;
    }

    private function allParticipantsResponded(Trip $trip): bool
    {
        $bookings = $this->bookingRepository->findConfirmedParticipantsForTrip($trip);

        foreach ($bookings as $booking) {
            if ($booking->getRespondedAt() === null) {
                return false;
            }
        }

        return true;
    }

    private function tripHasOpenIssues(Trip $trip): bool
    {
        return $this->tripIssueRepository->findOneBy([
            'trip' => $trip,
            'status' => TripIssue::STATUS_OPEN,
        ]) instanceof TripIssue;
    }

    private function applyEligibleDriverCredits(Trip $trip): void
    {
        $driver = $trip->getDriver();

        if (!$driver instanceof User || $driver->getId() === null) {
            throw new TripParticipationException('Le conducteur de ce trajet est introuvable.');
        }

        $lockedDriver = $this->entityManager->find(User::class, $driver->getId(), LockMode::PESSIMISTIC_WRITE);

        if (!$lockedDriver instanceof User) {
            throw new TripParticipationException('Le conducteur de ce trajet est introuvable.');
        }

        $driverBalanceCents = $this->creditsToCents((string) $lockedDriver->getCreditBalance());
        $creditDelta = 0;

        foreach ($this->bookingRepository->findConfirmedParticipantsForTrip($trip) as $booking) {
            if ($booking->isPayoutApplied() || $booking->getOutcomeStatus() !== Booking::OUTCOME_CONFIRMED_GOOD) {
                continue;
            }

            $creditDelta += $this->creditsToCents((string) $booking->getDriverCreditAmount());
            $booking->setPayoutApplied(true);
        }

        if ($creditDelta > 0) {
            $lockedDriver->setCreditBalance($this->centsToCredits($driverBalanceCents + $creditDelta));
        }
    }

    private function createReviewFromValidation(
        Trip $trip,
        User $author,
        User $driver,
        int $rating,
        string $comment
    ): void {
        if ($trip->getId() === null) {
            throw new TripParticipationException('Impossible d enregistrer votre avis pour ce trajet.');
        }

        $existingReview = $this->reviewRepository->findOneByTripAuthorAndDriver($trip->getId(), $author, $driver);

        if ($existingReview instanceof Review) {
            $existingReview->setRating($rating);
            $existingReview->setComment($comment);
            $existingReview->setStatus(Review::STATUS_PENDING);
            $existingReview->setModeratedBy(null);
            $existingReview->setModeratedAt(null);
            $existingReview->setModerationNote(null);

            return;
        }

        $review = new Review();
        $review->setTrip($trip);
        $review->setAuthor($author);
        $review->setUser($driver);
        $review->setRating($rating);
        $review->setComment($comment);
        $review->setStatus(Review::STATUS_PENDING);

        $this->entityManager->persist($review);
    }

    private function isReviewableTripStatus(?string $status): bool
    {
        return in_array($status, [Trip::STATUS_COMPLETED, Trip::STATUS_DISPUTED], true);
    }

    private function isPlannedTripStatus(?string $status): bool
    {
        return in_array($status, [Trip::STATUS_PLANNED, 'planifie'], true);
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
