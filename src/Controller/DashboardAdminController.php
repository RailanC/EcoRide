<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Repository\TripIssueRepository;
use App\Repository\TripRepository;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DashboardAdminController extends AbstractController
{
    #[Route('/dashboard/admin', name: 'app_dashboard_admin')]
    public function index(
        UserRepository $userRepository,
        TripRepository $tripRepository,
        BookingRepository $bookingRepository,
        ReviewRepository $reviewRepository,
        TripIssueRepository $tripIssueRepository,
        VehicleRepository $vehicleRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $allUsers = $userRepository->findAll();
        $users = $userRepository->findAllExceptCurrent($this->getUser());
        $platformStats = [
            'users' => count($allUsers),
            'admins' => count(array_filter($allUsers, static fn (User $user): bool => in_array('ROLE_ADMIN', $user->getRoles(), true))),
            'employeurs' => count(array_filter($allUsers, static fn (User $user): bool => in_array('ROLE_EMPLOYEUR', $user->getRoles(), true))),
            'members' => count(array_filter($allUsers, static fn (User $user): bool => !in_array('ROLE_ADMIN', $user->getRoles(), true) && !in_array('ROLE_EMPLOYEUR', $user->getRoles(), true))),
            'verifiedUsers' => count(array_filter($allUsers, static fn (User $user): bool => $user->isVerified())),
            'trips' => $tripRepository->count([]),
            'bookings' => $bookingRepository->count([]),
            'reviews' => $reviewRepository->count([]),
            'openIssues' => count($tripIssueRepository->findOpenIssues()),
            'vehicles' => $vehicleRepository->count([]),
        ];

        return $this->render('dashboard_admin/index.html.twig', [
            'users' => $users,
            'platformStats' => $platformStats,
        ]);
    }

    #[Route('/dashboard/admin/users/{id}/edit', name: 'app_dashboard_admin_edit_user', methods: ['POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('edit_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        $username = trim((string) $request->request->get('username', ''));
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $role = (string) $request->request->get('role', 'ROLE_USER');

        if ($username === '' || $email === '') {
            $this->addFlash('error', 'Le nom d\'utilisateur et l\'email sont obligatoires.');

            return $this->redirectToRoute('app_dashboard_admin');
        }

        if (!in_array($role, ['ROLE_USER', 'ROLE_EMPLOYEUR', 'ROLE_ADMIN'], true)) {
            $this->addFlash('error', 'Le rôle sélectionné est invalide.');

            return $this->redirectToRoute('app_dashboard_admin');
        }

        if ($user->getEmail() !== $email) {
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cette adresse email est déjà utilisée.');

                return $this->redirectToRoute('app_dashboard_admin');
            }
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles($role === 'ROLE_USER' ? [] : [$role]);

        if ($password !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $password));
        }

        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur mis à jour avec succès.');

        return $this->redirectToRoute('app_dashboard_admin');

    }

    #[Route('/dashboard/admin/users/{id}/delete', name: 'app_dashboard_admin_delete_user', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_dashboard_admin');
        }

        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_dashboard_admin');
    }
}
