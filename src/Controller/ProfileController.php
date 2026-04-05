<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if (!$this->isCsrfTokenValid('delete_profile', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
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
            }
        }

        foreach ($user->getVehicles() as $vehicle) {
            if ($vehicle instanceof Vehicle) {
                $entityManager->remove($vehicle);
            }
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $tokenStorage->setToken(null);

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
