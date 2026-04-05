<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\TripParticipationException;
use App\Repository\BookingRepository;
use App\Service\BookingCancellationLinkSigner;
use App\Service\TripParticipationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    #[Route('/covoiturage/{id}/annuler-participation', name: 'app_covoiturage_cancel_participation', methods: ['POST'])]
    public function cancelParticipation(
        Request $request,
        Trip $trip,
        BookingRepository $bookingRepository,
        TripParticipationService $tripParticipationService,
    ): Response {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_participation_' . $trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $booking = $bookingRepository->findOneBy([
            'trip' => $trip,
            'user' => $currentUser,
            'confirmation' => true,
        ]);

        if (!$booking instanceof Booking) {
            $this->addFlash('info', 'Aucune participation active n a ete trouvee pour ce trajet.');

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        try {
            $wasCanceled = $tripParticipationService->cancelParticipation($booking);
        } catch (TripParticipationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
        }

        if ($wasCanceled) {
            $this->addFlash('success', 'Votre participation a bien ete annulee.');
        } else {
            $this->addFlash('info', 'Cette participation etait deja annulee ou ne peut plus etre modifiee.');
        }

        return $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()]);
    }

    #[Route('/booking/{id}/cancel-from-email', name: 'app_booking_cancel_from_email', methods: ['GET'])]
    public function cancelFromEmail(
        Request $request,
        Booking $booking,
        BookingCancellationLinkSigner $bookingCancellationLinkSigner,
        TripParticipationService $tripParticipationService,
    ): Response {
        $trip = $booking->getTrip();

        if (!$bookingCancellationLinkSigner->validateSignedRequest($request)) {
            $this->addFlash('error', 'Ce lien de suppression est invalide ou a expire.');

            return $trip !== null
                ? $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()])
                : $this->redirectToRoute('app_home');
        }

        try {
            $wasCanceled = $tripParticipationService->cancelParticipation($booking);
        } catch (TripParticipationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $trip !== null
                ? $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()])
                : $this->redirectToRoute('app_home');
        }

        if ($wasCanceled) {
            $this->addFlash('success', 'Votre participation a bien ete annulee.');
        } else {
            $this->addFlash('info', 'Cette participation etait deja annulee ou ne peut plus etre modifiee.');
        }

        return $trip !== null
            ? $this->redirectToRoute('app_covoiturage_show', ['id' => $trip->getId()])
            : $this->redirectToRoute('app_home');
    }
}
