<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\VoitureRepository;
use App\Repository\CovoiturageRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ParticipationRepository;
use App\Entity\Participation;
use App\Entity\Covoiturage;

final class CovoiturageController extends AbstractController
{
    #[Route('/covoiturages', name: 'app_covoiturage', methods: ['GET'])]
    public function index(Request $request, CovoiturageRepository $covoiturageRepository): Response
    {
        $departure   = $request->query->get('departure');
        $arrival     = $request->query->get('arrival');
        $date        = $request->query->get('date');
        $passengers  = $request->query->get('passengers');
        $passengers = ($passengers !== null && $passengers !== '') ? (int) $passengers : null;
        $eco         = $request->query->getBoolean('eco');
        $maxPrice = $request->query->get('max_price');
        $maxPrice = ($maxPrice !== null && $maxPrice !== '') ? (float) $maxPrice : null;
        $maxDuration = $request->query->get('max_duration');
        $maxDuration = ($maxDuration !== null && $maxDuration !== '') ? (int) $maxDuration : null;
        $minRating   = $request->query->get('min_rating');
        $minRating = ($minRating !== null && $minRating !== '') ? (float) $minRating : null;
        $hasFilters = $departure ||
        $arrival ||
        $date ||
        $passengers ||
        $eco ||
        $maxPrice ||
        $maxDuration ||
        $minRating;

        
        $covoiturages = $hasFilters
        ? $covoiturageRepository->searchAvailableCovoiturages(
            $departure,
            $arrival,
            $date,
            $passengers,
            $eco,
            $maxPrice,
            $maxDuration,
            $minRating
        )
        : $covoiturageRepository->findAvailableCovoiturages();
        
        return $this->render('covoiturage/index.html.twig', [
            'covoiturages' => $covoiturages,
            'from' =>  $departure,
            'to' => $arrival,
            'date' => $date
        ]);
    }

    #[Route('/covoiturages/new', name: 'app_covoiturage_new')]
    public function new(VoitureRepository $voitureRepository): Response
    {
        $voitures = $voitureRepository->findAll();
        return $this->render('covoiturage/new.html.twig', [
            'voitures' => $voitures
        ]);
    }

    #[Route('/covoiturages/{id}', name: 'app_covoiturage_show', methods: ['GET'])]
    public function show(Covoiturage $covoiturage, ParticipationRepository $participationRepository): Response
    {
        $user = $this->getUser();
        $participation = false;

        if ($user) {
            $participation = (bool) $participationRepository->findOneBy([
                'utilisateur' => $user,
                'covoiturage' => $covoiturage,
            ]);
        }

        return $this->render('covoiturage/show.html.twig', [
            'covoiturage' => $covoiturage,
            'participation' => $participation,
        ]);
    }
    
    #[Route('/covoiturage/{id}/participer', name: 'app_covoiturage_participer', methods: ['GET'])]
    public function participer(Covoiturage $covoiturage): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($covoiturage->getTotalPlaces() <= 0) {
            $this->addFlash('error', 'Il n\'y a plus de places disponibles.');
            return $this->redirectToRoute('app_covoiturage_show', ['id' => $covoiturage->getId()]);
        }

        $prixCredits = $covoiturage->getPrixPersonne();

        if ($user->getCredit() < $prixCredits) {
            $this->addFlash('error', 'Vous n\'avez pas assez de crédits.');
            return $this->redirectToRoute('app_covoiturage_show', ['id' => $covoiturage->getId()]);
        }

        return $this->render('covoiturage/confirm_participation.html.twig', [
            'covoiturage' => $covoiturage,
            'prixCredits' => $prixCredits,
        ]);
    }

    #[Route('/covoiturage/{id}/participer/confirm', name: 'app_covoiturage_participer_confirm', methods: ['POST'])]
    public function confirmerParticipation(
        Request $request,
        Covoiturage $covoiturage,
        EntityManagerInterface $em,
        ParticipationRepository $participationRepository
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('participer_'.$covoiturage->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($covoiturage->getTotalPlaces() <= 0) {
            $this->addFlash('error', 'Il n\'y a plus de places disponibles.');
            return $this->redirectToRoute('app_covoiturage_show', ['id' => $covoiturage->getId()]);
        }

        $prixCredits = (int) $covoiturage->getPrixPersonne();

        if ($user->getCredit() < $prixCredits) {
            $this->addFlash('error', 'Vous n\'avez pas assez de crédits.');
            return $this->redirectToRoute('app_covoiturage_show', ['id' => $covoiturage->getId()]);
        }

        $existingParticipation = $participationRepository->findOneBy([
            'utilisateur' => $user,
            'covoiturage' => $covoiturage,
        ]);

        if ($existingParticipation) {
            $this->addFlash('error', 'Vous participez déjà à ce trajet.');
            return $this->redirectToRoute('app_covoiturage_show', ['id' => $covoiturage->getId()]);
        }

        $participation = new Participation();
        $participation->setUtilisateur($user);
        $participation->setCovoiturage($covoiturage);
        $participation->setConfirmation(true);
        $participation->setCreditsUtilise($prixCredits);
        $participation->setStatus('confirmée');

        $user->setCredit($user->getCredit() - $prixCredits);
        $covoiturage->setTotalPlaces($covoiturage->getTotalPlaces() - 1);

        $em->persist($participation);
        $em->persist($user);
        $em->persist($covoiturage);
        $em->flush();

        $this->addFlash('success', 'Votre participation a été confirmée.');

        return $this->redirectToRoute('app_covoiturage_show', ['id' => $covoiturage->getId()]);
    }
}
