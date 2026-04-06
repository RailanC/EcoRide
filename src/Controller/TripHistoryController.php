<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TripHistoryController extends AbstractController
{
    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $url = $this->generateUrl('app_profile_trips', array_filter([
            'mode' => $request->query->get('mode'),
            'tab' => $request->query->get('tab'),
            'q' => $request->query->get('q'),
        ], static fn ($value): bool => $value !== null && $value !== ''));

        return $this->redirect($url . '#history');
    }
}
