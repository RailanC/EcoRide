<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\TripIssue;
use App\Entity\User;
use App\Exception\TripParticipationException;
use App\Form\TripIssueResolutionFormType;
use App\Repository\ReviewRepository;
use App\Repository\TripIssueRepository;
use App\Service\TripParticipationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(ReviewRepository $reviewRepository, TripIssueRepository $tripIssueRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        $pendingReviews = $reviewRepository->findPendingReviews();
        $pendingIssues = $tripIssueRepository->findOpenIssues();

        return $this->render('dashboard/index.html.twig', [
            'pendingReviews' => $pendingReviews,
            'pendingIssues' => $pendingIssues,
        ]);
    }

    #[Route('/dashboard/reviews', name: 'app_reviews')]
    public function review(ReviewRepository $reviewRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        $reviews = $reviewRepository->findAllReviews();

        return $this->render('dashboard/reviews.html.twig', [
            'reviews' => $reviews,
            'reviewStatusLabels' => [
                Review::STATUS_PENDING => 'En attente',
                Review::STATUS_APPROVED => 'Approuve',
                Review::STATUS_REJECTED => 'Rejete',
            ],
        ]);
    }

    #[Route('/dashboard/reviews/approve/{id}', name: 'app_approve_review', methods: ['POST'])]
    public function approveReview(Request $request, TripParticipationService $tripParticipationService, ReviewRepository $reviewRepository, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        if (!$this->isCsrfTokenValid('approve_review_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_dashboard');
        }

        $review = $reviewRepository->find($id);
        $moderator = $this->getUser();

        if (!$review instanceof Review || !$moderator instanceof User) {
            $this->addFlash('error', 'Avis ou utilisateur introuvable.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $tripParticipationService->moderateReview($review, $moderator, Review::STATUS_APPROVED);
            $this->addFlash('success', 'Avis approuve avec succes.');
        } catch (TripParticipationException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/reviews/reject/{id}', name: 'app_reject_review', methods: ['POST'])]
    public function rejectReview(Request $request, TripParticipationService $tripParticipationService, ReviewRepository $reviewRepository, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        if (!$this->isCsrfTokenValid('reject_review_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_dashboard');
        }

        $review = $reviewRepository->find($id);
        $moderator = $this->getUser();
        $note = $request->request->get('moderation_note', '');

        if (!$review instanceof Review || !$moderator instanceof User) {
            $this->addFlash('error', 'Avis ou utilisateur introuvable.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $tripParticipationService->moderateReview($review, $moderator, Review::STATUS_REJECTED, $note);
            $this->addFlash('success', 'Avis rejete avec succes.');
        } catch (TripParticipationException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/reports', name: 'app_reports')]
    public function reports(TripIssueRepository $tripIssueRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        $reports = $tripIssueRepository->findAllIssues();

        return $this->render('dashboard/reports.html.twig', [
            'reports' => $reports,
            'reportStatusLabels' => [
                TripIssue::STATUS_OPEN => 'Ouvert',
                TripIssue::STATUS_RESOLVED_FOR_DRIVER => 'Resolu conducteur',
                TripIssue::STATUS_RESOLVED_AGAINST_DRIVER => 'Resolu passager',
            ],
        ]);
    }

    #[Route('/dashboard/reports/{id}', name: 'app_report', methods: ['GET'])]
    public function tripIssueDetails(TripIssueRepository $tripIssueRepository, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        $issue = $tripIssueRepository->find($id);

        if (!$issue instanceof TripIssue) {
            throw $this->createNotFoundException('Signalement introuvable.');
        }

        return $this->render('dashboard/tripIssueDetails.html.twig', [
            'issue' => $issue,
            'issueStatusLabels' => [
                TripIssue::STATUS_OPEN => 'Ouvert',
                TripIssue::STATUS_RESOLVED_FOR_DRIVER => 'Résolu en faveur du conducteur',
                TripIssue::STATUS_RESOLVED_AGAINST_DRIVER => 'Résolu en faveur du passager',
            ],
            'resolutionForm' => $this->createForm(TripIssueResolutionFormType::class)->createView(),
        ]);
    }

    #[Route('/dashboard/reports/{id}/resolve', name: 'app_tripIssue_resolve', methods: ['POST'])]
    public function resolveTripIssue(
        Request $request,
        TripParticipationService $tripParticipationService,
        TripIssueRepository $tripIssueRepository,
        int $id
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EMPLOYEUR');

        $issue = $tripIssueRepository->find($id);
        $moderator = $this->getUser();

        if (!$issue instanceof TripIssue || !$moderator instanceof User) {
            $this->addFlash('error', 'Signalement ou utilisateur introuvable.');

            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(TripIssueResolutionFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez verifier vos choix.');

            return $this->redirectToRoute('app_tripIssue', ['id' => $issue->getId()]);
        }

        $data = (array) $form->getData();
        $resolution = (string) ($data['resolution'] ?? '');
        $resolutionNote = (string) ($data['resolutionNote'] ?? '');

        try {
            $tripParticipationService->resolveIssue($issue, $moderator, $resolution, $resolutionNote);
            $this->addFlash('success', 'Signalement resolu avec succes.');
        } catch (TripParticipationException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_tripIssue', ['id' => $issue->getId()]);
    }
}
