<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TripRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TripHistoryController extends AbstractController
{
    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function index(Request $request, TripRepository $tripRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $isDriver = in_array($user->getType(), ['driver', 'both'], true);
        $isPassenger = in_array($user->getType(), ['passenger', 'both'], true);
        $availableModes = array_values(array_filter([
            $isDriver ? 'driver' : null,
            $isPassenger ? 'passenger' : null,
        ]));
        $selectedMode = (string) $request->query->get('mode', $availableModes[0] ?? 'passenger');
        $selectedMode = in_array($selectedMode, $availableModes, true) ? $selectedMode : ($availableModes[0] ?? 'passenger');
        $selectedTab = (string) $request->query->get('tab', TripRepository::HISTORY_TAB_ALL);
        $selectedTab = in_array(
            $selectedTab,
            [TripRepository::HISTORY_TAB_ALL, TripRepository::HISTORY_TAB_ACTIVE, TripRepository::HISTORY_TAB_OLD],
            true
        ) ? $selectedTab : TripRepository::HISTORY_TAB_ALL;
        $search = trim((string) $request->query->get('q', ''));
        $isHistoryAvailable = $isDriver || $isPassenger;

        return $this->render('history/index.html.twig', [
            'isDriver' => $isDriver,
            'isPassenger' => $isPassenger,
            'isHistoryAvailable' => $isHistoryAvailable,
            'selectedMode' => $selectedMode,
            'availableModes' => $availableModes,
            'selectedTab' => $selectedTab,
            'search' => $search,
            'tabCounts' => !$isHistoryAvailable ? [
                TripRepository::HISTORY_TAB_ALL => 0,
                TripRepository::HISTORY_TAB_ACTIVE => 0,
                TripRepository::HISTORY_TAB_OLD => 0,
            ] : ($selectedMode === 'driver'
                ? $tripRepository->countDriverHistoryTrips($user)
                : $tripRepository->countPassengerHistoryTrips($user)),
            'trips' => !$isHistoryAvailable ? [] : ($selectedMode === 'driver'
                ? $tripRepository->findDriverHistoryTrips($user, $selectedTab, $search)
                : $tripRepository->findPassengerHistoryTrips($user, $selectedTab, $search)),
        ]);
    }
}
