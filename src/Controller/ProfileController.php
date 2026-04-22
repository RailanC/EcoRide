<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\TripIssue;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Form\ProfileFormType;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à votre profil.');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $brands = $entityManager->getRepository(Brand::class)->findAll();

        return $this->render('profile/index.html.twig', [
            'profileForm' => $form->createView(),
            'brands' => $brands,
        ]);
    }

    #[Route('/profile/trips', name: 'app_profile_trips', methods: ['GET'])]
    public function trips(
        Request $request,
        TripRepository $tripRepository,
        BookingRepository $bookingRepository,
        ReviewRepository $reviewRepository,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez etre connecte pour acceder a cette page.');
        }

        $isDriver = in_array($user->getType(), ['driver', 'both'], true);
        $isPassenger = in_array($user->getType(), ['passenger', 'both'], true);
        $availableModes = array_values(array_filter([
            $isDriver ? 'driver' : null,
            $isPassenger ? 'passenger' : null,
        ]));
        $selectedMode = (string) $request->query->get('mode', $availableModes[0] ?? 'passenger');
        $selectedMode = in_array($selectedMode, $availableModes, true) ? $selectedMode : ($availableModes[0] ?? 'passenger');
        $selectedTab = (string) $request->query->get('tab', TripRepository::HISTORY_TAB_ALL);
        $selectedTab = in_array(
            $selectedTab,
            [TripRepository::HISTORY_TAB_ALL, TripRepository::HISTORY_TAB_ACTIVE, TripRepository::HISTORY_TAB_OLD],
            true
        ) ? $selectedTab : TripRepository::HISTORY_TAB_ALL;
        $search = trim((string) $request->query->get('q', ''));
        $isHistoryAvailable = $isDriver || $isPassenger;

        return $this->render('profile/trips.html.twig', [
            'driverTrips' => $tripRepository->findActiveDrivenTrips($user),
            'pendingConfirmations' => $bookingRepository->findPendingOutcomeBookingsForUser($user),
            'authoredReviews' => $reviewRepository->findAuthoredReviewsForUser($user),
            'receivedDriverReviews' => $reviewRepository->findReceivedReviewsForDriver($user),
            'isDriver' => $isDriver,
            'isPassenger' => $isPassenger,
            'isHistoryAvailable' => $isHistoryAvailable,
            'selectedMode' => $selectedMode,
            'availableModes' => $availableModes,
            'selectedTab' => $selectedTab,
            'search' => $search,
            'historyTabCounts' => !$isHistoryAvailable ? [
                TripRepository::HISTORY_TAB_ALL => 0,
                TripRepository::HISTORY_TAB_ACTIVE => 0,
                TripRepository::HISTORY_TAB_OLD => 0,
            ] : ($selectedMode === 'driver'
                ? $tripRepository->countDriverHistoryTrips($user)
                : $tripRepository->countPassengerHistoryTrips($user)),
            'historyTrips' => !$isHistoryAvailable ? [] : ($selectedMode === 'driver'
                ? $tripRepository->findDriverHistoryTrips($user, $selectedTab, $search)
                : $tripRepository->findPassengerHistoryTrips($user, $selectedTab, $search)),
        ]);
    }

    #[Route('/profile', name: 'app_profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash(
                    'error',
                    'Erreur sur le champ "' . ($error->getOrigin()?->getName() ?? 'inconnu') . '" : ' . $error->getMessage()
                );
            }

            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez les corriger.');

            return $this->redirectToRoute('app_profile', [], 303);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            if (!empty($currentPassword) || !empty($newPassword)) {
                if (empty($currentPassword) || empty($newPassword)) {
                    $this->addFlash('error', 'Veuillez remplir tous les champs du changement de mot de passe.');

                    return $this->redirectToRoute('app_profile', [], 303);
                }

                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Le mot de passe actuel est incorrect.');

                    return $this->redirectToRoute('app_profile', [], 303);
                }

                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $this->addFlash('success', 'Votre mot de passe a été mis à jour.');
            }

            $role = $request->request->get('role');
            if (is_string($role) && in_array($role, ['passenger', 'driver', 'both'], true)) {
                $user->setType($role);
            }

            foreach ($user->getVehicles() as $vehicle) {
                $vehicle->setOwner($user);
            }

            foreach ($form->get('vehicles') as $vehicleForm) {
                /** @var Vehicle|null $vehicle */
                $vehicle = $vehicleForm->getData();

                if ($vehicle === null) {
                    continue;
                }

                $preferencesData = $vehicleForm->get('preferences')->getData();

                if (is_string($preferencesData) && $preferencesData !== '') {
                    $preferencesData = json_decode($preferencesData, true);
                }

                $vehicle->setPreferences(
                    is_array($preferencesData)
                        ? array_merge([
                            'smoking' => 0,
                            'animals' => 0,
                            'custom' => [],
                        ], $preferencesData)
                        : [
                            'smoking' => 0,
                            'animals' => 0,
                            'custom' => [],
                        ]
                );
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour.');

            return $this->redirectToRoute('app_profile', [], 303);
        }

        return $this->redirectToRoute('app_profile', [], 303);
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteProfile(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if (!$this->isCsrfTokenValid('delete_profile', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_profile');
        }

        foreach ($user->getBookings() as $booking) {
            if ($booking instanceof Booking) {
                $booking->setUser(null);
            }
        }

        foreach ($user->getTrips() as $trip) {
            if ($trip instanceof Trip) {
                $trip->setDriver(null);
            }
        }

        foreach ($user->getReviews() as $review) {
            if ($review instanceof Review) {
                $review->setUser(null);
                $review->setAuthor(null);
                $review->setModeratedBy(null);
            }
        }

        $tripIssueRepository = $entityManager->getRepository(TripIssue::class);

        foreach ($tripIssueRepository->findBy(['participant' => $user]) as $tripIssue) {
            if ($tripIssue instanceof TripIssue) {
                $tripIssue->setParticipant(null);
            }
        }

        foreach ($tripIssueRepository->findBy(['driver' => $user]) as $tripIssue) {
            if ($tripIssue instanceof TripIssue) {
                $tripIssue->setDriver(null);
            }
        }

        foreach ($tripIssueRepository->findBy(['resolvedBy' => $user]) as $tripIssue) {
            if ($tripIssue instanceof TripIssue) {
                $tripIssue->setResolvedBy(null);
            }
        }

        foreach ($user->getVehicles() as $vehicle) {
            if ($vehicle instanceof Vehicle) {
                $entityManager->remove($vehicle);
            }
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $session = $request->getSession();
        if ($session !== null) {
            $session->invalidate();
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/profile/vehicles/{id}/delete', name: 'app_profile_vehicle_delete', methods: ['POST'])]
    public function deleteVehicle(
        Vehicle $vehicle,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez etre connecte pour acceder a cette page.');
        }

        if ($vehicle->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce vehicule.');
        }

        if (!$this->isCsrfTokenValid('delete_vehicle_' . $vehicle->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (!$vehicle->getTrips()->isEmpty()) {
            $this->addFlash('error', 'Ce vehicule est lie a un ou plusieurs covoiturages et ne peut pas etre supprime.');

            return $this->redirectToRoute('app_profile');
        }

        $user->removeVehicle($vehicle);
        $entityManager->remove($vehicle);
        $entityManager->flush();

        $this->addFlash('success', 'Le vehicule a ete supprime.');

        return $this->redirectToRoute('app_profile');
    }
}
