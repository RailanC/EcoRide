<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route('/news', name: 'app_news')]
    public function index(): Response
    {
        return $this->render('news/index.html.twig');
    }

    #[Route('/news/impact-climat', name: 'app_news_article_climate')]
    public function climateImpact(): Response
    {
        return $this->render('news/article_climate.html.twig');
    }

    #[Route('/news/emissions-quotidiennes', name: 'app_news_article_emissions')]
    public function dailyEmissions(): Response
    {
        return $this->render('news/article_emissions.html.twig');
    }

    #[Route('/news/plateformes-electriques', name: 'app_news_article_platforms')]
    public function electricPlatforms(): Response
    {
        return $this->render('news/article_platforms.html.twig');
    }
}
