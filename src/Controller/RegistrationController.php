<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private string $mailerFromAddress,
        private string $mailerFromName,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            $user->setIsVerified(false);

            if ($user->getTelephone() === null) {
                $user->setTelephone('');
            }

            if ($user->getAdresse() === null) {
                $user->setAdresse('');
            }

            if ($user->getPseudo() === null) {
                $user->setPseudo($user->getPrenom() . $user->getNom());
            }

            if ($user->getRoles() === []) {
                $user->setRoles(['ROLE_USER']);
            }

            if ($user->getDateNaissance() === null) {
                $user->setDateNaissance(new \DateTime('2000-01-01'));
            }

            if ($user->getPhoto() === null) {
                $user->setPhoto('');
            }

            $user->setRoles(['ROLE_USER']);
            $user->setCredit(20.00);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to(new Address((string) $user->getEmail()))
                    ->subject('Veuillez confirmer votre email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            $this->addFlash('success', 'Inscription réussie. Vérifiez votre email pour confirmer votre compte.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): Response {
        $id = $request->query->get('id');

        if (!$id) {
            $this->addFlash('verify_email_error', 'Identifiant de vérification manquant.');
            return $this->redirectToRoute('app_register');
        }

        $user = $entityManager->getRepository(Utilisateur::class)->find($id);

        if (!$user) {
            $this->addFlash('verify_email_error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_register');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'verify_email_error',
                $translator->trans($exception->getReason(), [], 'VerifyEmailBundle')
            );

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre adresse email a bien été vérifiée.');

        return $this->redirectToRoute('app_login');
    }
}