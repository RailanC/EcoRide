<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Voiture;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProfileFormType;
use Doctrine\Common\Collections\Collection;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à votre profil.');
        }

        $form = $this->createForm(ProfileFormType::class, $user);

        // Get available car brands for dropdowns
        $marques = $entityManager->getRepository(\App\Entity\Marque::class)->findAll();

        return $this->render('profile/index.html.twig', [
            'profileForm' => $form->createView(),
            'marques' => $marques,
        ]);
    }

    #[Route('/profile', name: 'app_profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        // Check for form validation errors
        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash(
                    'error',
                    'Erreur sur le champ "' . ($error->getOrigin()?->getName() ?? 'inconnu') . '": ' . $error->getMessage()
                );
            }

            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez les corriger.');
            return $this->redirectToRoute('app_profile', [], 303);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password update
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

            // Handle role selection (from manual HTML radio inputs)
            $role = $request->request->get('role');
            if ($role && in_array($role, ['passenger', 'driver', 'both'])) {
                $user->setType($role);
            }

            // Ensure all vehicles have the correct owner set
            foreach ($user->getVoitures() as $vehicle) {
                $vehicle->setUtilisateur($user);
            }

            // Handle preferences JSON from form data
            foreach ($form->get('voitures') as $vehicleForm) {
                /** @var \App\Entity\Voiture|null $voiture */
                $voiture = $vehicleForm->getData();

                if (!$voiture) {
                    continue;
                }

                $prefsData = $vehicleForm->get('preferences')->getData();

                if (is_string($prefsData) && $prefsData !== '') {
                    $prefsData = json_decode($prefsData, true);
                }

                $voiture->setPreferences(
                    is_array($prefsData)
                        ? array_merge([
                            'smoking' => 0,
                            'animals' => 0,
                            'custom' => [],
                        ], $prefsData)
                        : [
                            'smoking' => 0,
                            'animals' => 0,
                            'custom' => [],
                        ]
                );
            }

            // Symfony form automatically handles vehicle collection due to CollectionType + by_reference: false
            // The cascade: ['persist', 'remove'] and orphanRemoval: true in Utilisateur entity handle saves/deletes
            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour.');

            return $this->redirectToRoute('app_profile', [], 303);
        }

        return $this->redirectToRoute('app_profile', [], 303);
    }
}

