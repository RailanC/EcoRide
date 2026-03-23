<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\VoitureRepository;
use App\Repository\CovoiturageRepository;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/covoiturages/{id}', name: 'app_covoiturage_show')]
    public function show(Covoiturage $covoiturage): Response
    {
        return $this->render('covoiturage/show.html.twig', [
            'covoiturage' => $covoiturage
        ]);
    }
}
