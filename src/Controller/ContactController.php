<?php

namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $fromAddress = (string) $this->getParameter('mailer_from_address');
            $fromName = (string) $this->getParameter('mailer_from_name');

            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->replyTo(new Address($data['email'], $data['name']))
                ->to($fromAddress)
                ->subject(sprintf('Nouveau message de contact de %s', $data['name']))
                ->text(sprintf(
                    "Nom : %s\nEmail : %s\n\nMessage :\n%s",
                    $data['name'],
                    $data['email'],
                    $data['message']
                ));

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons bientôt.');
            } catch (TransportExceptionInterface $exception) {
                $this->addFlash('danger', 'Impossible d’envoyer le message pour le moment. Veuillez réessayer plus tard.');
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }
}
