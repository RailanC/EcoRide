<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\RouteEstimationException;
use App\Exception\TripParticipationException;
use App\Form\NewTripFormType;
use App\Form\TripFormType;
use App\Form\TripOutcomeFormType;
use App\Repository\BookingRepository;
use App\Repository\TripRepository;
use App\Service\TripParticipationService;
use App\Service\TripRouteEstimator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{
    #[Route('/covoiturages', name: 'app_trip', methods: ['GET'])]
    public function index(Request $request, TripRepository $tripRepository): Response
    {
        $createdTripId = $request->query->getInt('created');

        $form = $this->createForm(TripFormType::class, [
            'departure' => $request->query->get('departure'),
            'arrival' => $request->query->get('arrival'),
            'date' => $request->query->get('date'),
            'return_date' => $request->query->get('return_date'),
            'trip_type' => $request->query->get('trip_type'),
            'energyType' => $request->query->get('energyType', ''),
            'max_price' => $request->query->get('max_price', 100),
            'min_rating' => $request->query->get('min_rating', 1),
            'departure_time' => $request->query->get('departure_time'),
            'seats_available' => $request->query->get('seats_available', $request->query->get('passengers')),
        ]);
        $form->handleRequest($request);

        $filters = $form->getData();

        $departure = $this->normalizeStringFilter($filters['departure'] ?? null);
        $arrival = $this->normalizeStringFilter($filters['arrival'] ?? null);
        $date = $this->normalizeStringFilter($filters['date'] ?? null);
        $passengers = $filters['seats_available'] ?? $request->query->get('passengers');
        $passengers = ($passengers !== null && $passengers !== '') ? (int) $passengers : null;
        $energyType = $this->normalizeStringFilter($filters['energyType'] ?? null);
        $maxPrice = $request->query->get('max_price');
        $maxPrice = ($maxPrice !== null && $maxPrice !== '') ? (float) $maxPrice : null;
        $maxDuration = $request->query->get('max_duration');
        $maxDuration = ($maxDuration !== null && $maxDuration !== '') ? (int) $maxDuration : null;
        $minRating = $request->query->get('min_rating');
        $minRating = ($minRating !== null && $minRating !== '') ? (float) $minRating : null;
        $departureTime = $this->normalizeStringFilter($filters['departure_time'] ?? null);

        $hasFilters = $departure || $arrival || $date || $passengers || $energyType || $maxPrice || $maxDuration || $minRating || $departureTime;

        $trips = $hasFilters
            ? $tripRepository->searchAvailableTrips(
                $departure,
                $arrival,
                $date,
                $passengers,
                $energyType,
                $maxPrice,
                $maxDuration,
                $minRating,
                $departureTime
            )
            : $tripRepository->findAvailableTrips();

        if ($createdTripId > 0) {
            usort(
                $trips,
                static function (Trip $left, Trip $right) use ($createdTripId): int {
                    if ($left->getId() === $createdTripId) {
                        return -1;
                    }

                    if ($right->getId() === $createdTripId) {
                        return 1;
                    }

                    return 0;
                }
            );
        }

        return $this->render('trip/index.html.twig', [
            'form' => $form->createView(),
            'trips' => $trips,
            'departure' => $departure,
            'arrival' => $arrival,
            'date' => $date,
            'createdTripId' => $createdTripId > 0 ? $createdTripId : null,
        ]);
    }

    #[Route('/covoiturages/new', name: 'app_trip_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        TripRouteEstimator $tripRouteEstimator,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->render('trip/new.html.twig', [
                'vehicles' => [],
                'form' => null,
                'map_image_url' => "images/new_trip_map_placeholder.png",
            ]);
        }

        $vehicles = $user->getVehicles();

        if ($vehicles->isEmpty()) {
            return $this->render('trip/new.html.twig', [
                'vehicles' => $vehicles,
                'form' => null,
                'map_image_url' => "images/new_trip_map_placeholder.png",
            ]);
        }

        $trip = new Trip();

        if ($vehicles->count() === 1) {
            $trip->setVehicle($vehicles->first());
        }

        $form = $this->createForm(NewTripFormType::class, $trip, [
            'vehicles' => $vehicles->toArray(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Verifiez les champs ci-dessous.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $departureDateTime = $this->buildTripDateTime($trip->getDepartureDate(), $trip->getDepartureTime());

            if (!$departureDateTime instanceof \DateTimeImmutable) {
                $form->addError(new FormError('La date ou l heure de depart est invalide.'));
            } else {
                try {
                    $routeEstimate = $tripRouteEstimator->estimate(
                        (string) $trip->getDepartureLocation(),
                        (string) $trip->getArrivalLocation(),
                        $departureDateTime,
                    );

                    $estimatedArrival = $routeEstimate->getEstimatedArrival();

                    $trip->setArrivalDate(\DateTime::createFromImmutable($estimatedArrival));
                    $trip->setArrivalTime(\DateTime::createFromImmutable($estimatedArrival));
                } catch (RouteEstimationException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid() && $form->getErrors(true)->count() === 0) {
            $trip->setDriver($user);
            $trip->setStatus(Trip::STATUS_PLANNED);

            $entityManager->persist($trip);
            $entityManager->flush();

            $this->addFlash('success', 'Votre covoiturage a ete publie.');

            return $this->redirectToRoute('app_trip', [
                'departure' => $trip->getDepartureLocation(),
                'arrival' => $trip->getArrivalLocation(),
                'date' => $trip->getDepartureDate()?->format('Y-m-d'),
                'created' => $trip->getId()
            ]);
        }

        return $this->render('trip/new.html.twig', [
            'vehicles' => $vehicles,
            'form' => $form->createView(),
            'map_image_url' => "images/new_trip_map_placeholder.png",
        ]);
    }

    #[Route('/covoiturages/route-preview', name: 'app_trip_route_preview', methods: ['GET'])]
    public function routePreview(Request $request, TripRouteEstimator $tripRouteEstimator): JsonResponse
    {
        $departure = trim((string) $request->query->get('departure', ''));
        $destination = trim((string) $request->query->get('destination', ''));

        if ($departure === '' || $destination === '') {
            return $this->json([
                'error' => 'Veuillez renseigner une ville de depart et une destination.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $routeEstimate = $tripRouteEstimator->estimate(
                $departure,
                $destination,
                new \DateTimeImmutable('+24 hours')
            );
        } catch (RouteEstimationException $exception) {
            return $this->json([
                'error' => $this->toFrenchRouteMessage($exception->getMessage()),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'departureLabel' => $departure,
            'destinationLabel' => $destination,
            'distanceMeters' => $routeEstimate->getDistanceMeters(),
            'durationSeconds' => $routeEstimate->getDurationSeconds(),
            'geometry' => $routeEstimate->getGeometry(),
        ]);
    }

    #[Route('/covoiturages/{id}', name: 'app_trip_show', methods: ['GET'])]
    public function show(
        Trip $trip,
        BookingRepository $bookingRepository,
        TripParticipationService $tripParticipationService,
    ): Response
    {
        $currentUser = $this->getUser();
        $hasBooking = false;
        $priceInCredits = (string) $trip->getPricePerPerson();
        $participants = array_values(array_filter(
            $trip->getBookings()->toArray(),
            static fn (Booking $booking): bool => $booking->isConfirmation() === true && $booking->getUser() !== null
        ));

        if ($currentUser instanceof User) {
            $hasBooking = (bool) $bookingRepository->findOneBy([
                'user' => $currentUser,
                'trip' => $trip,
                'confirmation' => true,
            ]);
        }

        return $this->render('trip/show.html.twig', [
            'trip' => $trip,
            'hasBooking' => $hasBooking,
            'participants' => $participants,
            'priceInCredits' => $priceInCredits,
            'driverReceives' => $tripParticipationService->getDriverEarnings($priceInCredits),
        ]);
    }

    #[Route('/covoiturages/{id}/route-preview', name: 'app_trip_show_route_preview', methods: ['GET'])]
    public function showRoutePreview(Trip $trip, TripRouteEstimator $tripRouteEstimator): JsonResponse
    {
        $departureDateTime = $this->buildTripDateTime($trip->getDepartureDate(), $trip->getDepartureTime());

        if (!$departureDateTime instanceof \DateTimeImmutable) {
            return $this->json([
                'error' => 'Impossible de calculer la date de depart de ce trajet.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $routeEstimate = $tripRouteEstimator->estimate(
                (string) $trip->getDepartureLocation(),
                (string) $trip->getArrivalLocation(),
                $departureDateTime
            );
        } catch (RouteEstimationException $exception) {
            return $this->json([
                'error' => $this->toFrenchRouteMessage($exception->getMessage()),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'departureLabel' => $trip->getDepartureLocation(),
            'destinationLabel' => $trip->getArrivalLocation(),
            'distanceMeters' => $routeEstimate->getDistanceMeters(),
            'durationSeconds' => $routeEstimate->getDurationSeconds(),
            'geometry' => $routeEstimate->getGeometry(),
        ]);
    }

    #[Route('/covoiturages/{id}/delete', name: 'app_trip_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Trip $trip,
        TripParticipationService $tripParticipationService,
    ): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($trip->getDriver()?->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce trajet.');
        }

        if (!$this->isCsrfTokenValid('delete_trip_' . $trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $wasCanceled = $tripParticipationService->cancelTrip($trip, $currentUser);
        } catch (TripParticipationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if (!$wasCanceled) {
            $this->addFlash('info', 'Ce covoiturage etait deja annule.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        $this->addFlash('success', 'Votre covoiturage a ete annule et les participants ont ete rembourses.');

        return $this->redirectToRoute('app_trip');
    }

    #[Route('/covoiturage/{id}/participer', name: 'app_trip_participer', methods: ['GET'])]
    public function participate(
        Trip $trip,
        BookingRepository $bookingRepository,
        TripParticipationService $tripParticipationService,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($trip->getDriver()?->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas participer a votre propre trajet.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if ($trip->getStatus() !== Trip::STATUS_PLANNED) {
            $this->addFlash('error', 'Ce trajet n accepte plus de nouvelles participations.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if ($trip->getStatus() === Trip::STATUS_CANCELED) {
            $this->addFlash('error', 'Ce trajet est deja annule.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        if (($trip->getAvailableSeats() ?? 0) <= 0) {
            $this->addFlash('error', 'Il n y a plus de places disponibles.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        $existingBooking = $bookingRepository->findOneBy([
            'user' => $currentUser,
            'trip' => $trip,
            'confirmation' => true,
        ]);

        if ($existingBooking instanceof Booking) {
            $this->addFlash('error', 'Vous participez deja a ce trajet.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        $priceInCredits = (string) $trip->getPricePerPerson();

        if ((float) $currentUser->getCreditBalance() < (float) $priceInCredits) {
            $this->addFlash('error', 'Vous n avez pas assez de credits.');

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        return $this->render('trip/confirm_booking.html.twig', [
            'trip' => $trip,
            'priceInCredits' => $priceInCredits,
            'driverReceives' => $tripParticipationService->getDriverEarnings($priceInCredits),
            'platformFee' => $tripParticipationService->getPlatformFeeCredits(),
        ]);
    }

    #[Route('/covoiturage/{id}/participer/confirm', name: 'app_trip_participer_confirm', methods: ['POST'])]
    public function confirmParticipation(
        Request $request,
        Trip $trip,
        TripParticipationService $tripParticipationService,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('participer_' . $trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $tripParticipationService->confirmParticipation($trip, $currentUser);
        } catch (TripParticipationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }

        $this->addFlash('success', 'Votre participation a ete confirmee.');

        return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
    }

    #[Route('/covoiturages/{id}/start', name: 'app_trip_start', methods: ['POST'])]
    public function startTrip(
        Request $request,
        Trip $trip,
        TripParticipationService $tripParticipationService,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('start_trip_' . $trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $tripParticipationService->startTrip($trip, $currentUser);
            $this->addFlash('success', 'Le trajet est maintenant en cours.');
        } catch (TripParticipationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_profile_trips');
    }

    #[Route('/covoiturages/{id}/arrive', name: 'app_trip_arrive', methods: ['POST'])]
    public function markArrived(
        Request $request,
        Trip $trip,
        TripParticipationService $tripParticipationService,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('arrive_trip_' . $trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $tripParticipationService->markTripArrived($trip, $currentUser);
            $this->addFlash('success', 'Le trajet a ete marque comme arrive.');
        } catch (TripParticipationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_profile_trips');
    }

    #[Route('/covoiturages/{id}/validate', name: 'app_trip_validate', methods: ['GET', 'POST'])]
    public function validateTrip(
        Request $request,
        Trip $trip,
        BookingRepository $bookingRepository,
        TripParticipationService $tripParticipationService,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $booking = $bookingRepository->findParticipantBookingForTrip($currentUser, $trip);

        if (!$booking instanceof Booking) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas valider ce trajet.');
        }

        $form = $this->createForm(TripOutcomeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = (array) $form->getData();
            $score = (int) ($data['outcome'] ?? 0);
            $normalizedOutcome = $score >= 4 ? 'good' : 'bad';

            try {
                $tripParticipationService->submitTripOutcome(
                    $trip,
                    $currentUser,
                    $score,
                    $normalizedOutcome,
                    (string) ($data['comment'] ?? '')
                );
                $this->addFlash('success', 'Votre retour et votre avis ont bien ete enregistres.');

                return $this->redirectToRoute('app_profile_trips');
            } catch (TripParticipationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('trip/validate.html.twig', [
            'trip' => $trip,
            'booking' => $booking,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/covoiturages/{id}/review', name: 'app_trip_review', methods: ['GET', 'POST'])]
    public function reviewTrip(
        Trip $trip,
        BookingRepository $bookingRepository,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $booking = $bookingRepository->findParticipantBookingForTrip($currentUser, $trip);

        if (!$booking instanceof Booking) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas laisser un avis pour ce trajet.');
        }

        $this->addFlash('info', 'Votre avis est desormais collecte lors de la validation du trajet.');

        return $this->redirectToRoute('app_trip_validate', ['id' => $trip->getId()]);
    }

    private function buildTripDateTime(
        ?\DateTimeInterface $date,
        ?\DateTimeInterface $time,
    ): ?\DateTimeImmutable {
        if (!$date instanceof \DateTimeInterface || !$time instanceof \DateTimeInterface) {
            return null;
        }

        return new \DateTimeImmutable(sprintf(
            '%s %s',
            $date->format('Y-m-d'),
            $time->format('H:i:s')
        ));
    }

    private function normalizeStringFilter(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toFrenchRouteMessage(string $message): string
    {
        return match ($message) {
            'OpenRouteService API key is not configured.' => 'La cle OpenRouteService n est pas configuree.',
            'Unable to geocode the trip locations at the moment.' => 'Impossible de localiser les villes pour le moment.',
            'OpenRouteService geocoding returned an unexpected response.' => 'Le service de localisation a renvoye une reponse inattendue.',
            'Unable to estimate the route at the moment.' => 'Impossible de calculer l itineraire pour le moment.',
            'OpenRouteService directions returned an unexpected response.' => 'Le service d itineraire a renvoye une reponse inattendue.',
            'No driving route could be found for this trip.' => 'Aucun itineraire n a ete trouve pour ce trajet.',
            'The route summary returned by OpenRouteService is invalid.' => 'Le resume de l itineraire est invalide.',
            'The route geometry returned by OpenRouteService is invalid.' => 'La geometrie de l itineraire est invalide.',
            default => str_starts_with($message, 'Unable to find coordinates for ')
                ? 'Impossible de localiser l une des villes saisies.'
                : 'Impossible de previsualiser l itineraire pour le moment.',
        };
    }
}
