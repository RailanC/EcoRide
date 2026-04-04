<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\TripRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TripController extends AbstractController
{
    #[Route('/covoiturages', name: 'app_covoiturage', methods: ['GET'])]
    public function index(Request $request, TripRepository $tripRepository): Response
    {
        $departure = $request->query->get('departure');
        $arrival = $request->query->get('arrival');
        $date = $request->query->get('date');
        $passengers = $request->query->get('passengers');
        $passengers = ($passengers !== null && $passengers !== '') ? (int) $passengers : null;
        $eco = $request->query->getBoolean('eco');
        $maxPrice = $request->query->get('max_price');
        $maxPrice = ($maxPrice !== null && $maxPrice !== '') ? (float) $maxPrice : null;
        $maxDuration = $request->query->get('max_duration');
        $maxDuration = ($maxDuration !== null && $maxDuration !== '') ? (int) $maxDuration : null;
        $minRating = $request->query->get('min_rating');
        $minRating = ($minRating !== null && $minRating !== '') ? (float) $minRating : null;

        $hasFilters = $departure || $arrival || $date || $passengers || $eco || $maxPrice || $maxDuration || $minRating;

        $trips = $hasFilters
            ? $tripRepository->searchAvailableTrips(
                $departure,
                $arrival,
                $date,
                $passengers,
                $eco,
                $maxPrice,
                $maxDuration,
                $minRating
            )
            : $tripRepository->findAvailableTrips();

        return $this->render('trip/index.html.twig', [
            'trips' => $trips,
            'departure' => $departure,
            'arrival' => $arrival,
            'date' => $date,
        ]);
    }

    #[Route('/covoiturages/new', name: 'app_covoiturage_new')]
    public function new(VehicleRepository $vehicleRepository): Response
    {
        return $this->render('trip/new.html.twig', [
            'vehicles' => $vehicleRepository->findAll(),
        ]);
    }

    #[Route('/covoiturages/{id}', name: 'app_covoiturage_show', methods: ['GET'])]
    public function show(Trip $trip, BookingRepository $bookingRepository): Response
    {
        $currentUser = $this->getUser();
        $hasBooking = false;

        if ($currentUser instanceof User) {
            $hasBooking = (bool) $bookingRepository->findOneBy([
                'user' => $currentUser,
                'trip' => $trip,
            ]);
        }

        return $this->render('trip/show.html.twig', [
            'trip' => $trip,
            'hasBooking' => $hasBooking,
        ]);
    }

    #[Route('/covoiturage/{id}/participer', name: 'app_covoiturage_participer', methods: ['GET'])]
    public function participate(Trip $trip): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($trip->getAvailableSeats() <= 0) {
            $this->addFlash('error', 'Il n\'y a plus de places disponibles.');

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        $priceInCredits = $trip->getPricePerPerson();

        if ((float) $currentUser->getCreditBalance() < (float) $priceInCredits) {
            $this->addFlash('error', 'Vous n\'avez pas assez de crédits.');

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        return $this->render('trip/confirm_booking.html.twig', [
            'trip' => $trip,
            'priceInCredits' => $priceInCredits,
        ]);
    }

    #[Route('/covoiturage/{id}/participer/confirm', name: 'app_covoiturage_participer_confirm', methods: ['POST'])]
    public function confirmParticipation(
        Request $request,
        Trip $trip,
        EntityManagerInterface $entityManager,
        BookingRepository $bookingRepository
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('participer_' . $trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($trip->getAvailableSeats() <= 0) {
            $this->addFlash('error', 'Il n\'y a plus de places disponibles.');

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        $priceInCredits = (int) $trip->getPricePerPerson();

        if ((float) $currentUser->getCreditBalance() < $priceInCredits) {
            $this->addFlash('error', 'Vous n\'avez pas assez de crédits.');

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        $existingBooking = $bookingRepository->findOneBy([
            'user' => $currentUser,
            'trip' => $trip,
        ]);

        if ($existingBooking !== null) {
            $this->addFlash('error', 'Vous participez déjà à ce trajet.');

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        $booking = new Booking();
        $booking->setUser($currentUser);
        $booking->setTrip($trip);
        $booking->setConfirmation(true);
        $booking->setCreditsUsed($priceInCredits);
        $booking->setStatus('confirmée');

        $currentUser->setCreditBalance((string) ((float) $currentUser->getCreditBalance() - $priceInCredits));
        $trip->setAvailableSeats($trip->getAvailableSeats() - 1);

        $entityManager->persist($booking);
        $entityManager->persist($currentUser);
        $entityManager->persist($trip);
        $entityManager->flush();

        $this->addFlash('success', 'Votre participation a été confirmée.');

        return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
    }
}
