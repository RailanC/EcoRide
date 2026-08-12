<?php

namespace App\Controller;

use App\Form\SearchPillFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $searchForm = $this->createForm(SearchPillFormType::class, [
            'date' => new \DateTimeImmutable('today'),
            'passengers' => 1,
        ], [
            'action' => $this->generateUrl('app_trip'),
        ]);

        return $this->render('home/index.html.twig', [
            'searchForm' => $searchForm->createView(),
        ]);
    }
}
