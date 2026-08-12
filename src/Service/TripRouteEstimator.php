<?php

namespace App\Service;

use App\Exception\RouteEstimationException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TripRouteEstimator
{
    private const GEOCODING_ENDPOINT = 'https://api.openrouteservice.org/geocode/search';
    private const DIRECTIONS_ENDPOINT = 'https://api.openrouteservice.org/v2/directions/driving-car/geojson';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openRouteServiceApiKey,
    ) {
    }

    public function estimate(
        string $departureAddress,
        string $destinationAddress,
        \DateTimeInterface $departureDateTime,
    ): RouteEstimate {
        if (trim($this->openRouteServiceApiKey) === '') {
            throw new RouteEstimationException('OpenRouteService API key is not configured.');
        }

        $departureCoordinates = $this->geocodeLocation($departureAddress);
        $destinationCoordinates = $this->geocodeLocation($destinationAddress);
        $route = $this->fetchRoute($departureCoordinates, $destinationCoordinates);

        if (!is_array($route)) {
            throw new RouteEstimationException('No driving route could be found for this trip.');
        }

        $summary = $route['summary'] ?? null;
        $geometry = $route['geometry'] ?? null;

        if (!is_array($summary)) {
            throw new RouteEstimationException('The route summary returned by OpenRouteService is invalid.');
        }

        if (!$this->isValidGeoJsonLineString($geometry)) {
            throw new RouteEstimationException('The route geometry returned by OpenRouteService is invalid.');
        }

        $durationSeconds = $summary['duration'] ?? null;
        $distanceMeters = $summary['distance'] ?? null;

        if (!is_numeric($durationSeconds) || (float) $durationSeconds < 0) {
            throw new RouteEstimationException('The route duration returned by OpenRouteService is invalid.');
        }

        if (!is_numeric($distanceMeters) || (float) $distanceMeters < 0) {
            throw new RouteEstimationException('The route distance returned by OpenRouteService is invalid.');
        }

        $estimatedArrival = \DateTimeImmutable::createFromInterface($departureDateTime)
            ->modify(sprintf('+%d seconds', (int) round((float) $durationSeconds)));

        if (!$estimatedArrival instanceof \DateTimeImmutable) {
            throw new RouteEstimationException('Unable to compute the estimated arrival time.');
        }

        return new RouteEstimate(
            (int) round((float) $durationSeconds),
            (int) round((float) $distanceMeters),
            $estimatedArrival,
            $geometry
        );
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function geocodeLocation(string $location): array
    {
        try {
            $response = $this->httpClient->request('GET', self::GEOCODING_ENDPOINT, [
                'headers' => [
                    'Authorization' => $this->openRouteServiceApiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'text' => $location,
                    'size' => 1,
                ],
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new RouteEstimationException('Unable to geocode the trip locations at the moment.', 0, $exception);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RouteEstimationException('OpenRouteService geocoding returned an unexpected response.');
        }

        $coordinates = $data['features'][0]['geometry']['coordinates'] ?? null;

        if (
            !is_array($coordinates)
            || count($coordinates) < 2
            || !is_numeric($coordinates[0])
            || !is_numeric($coordinates[1])
        ) {
            throw new RouteEstimationException(sprintf('Unable to find coordinates for "%s".', $location));
        }

        return [(float) $coordinates[0], (float) $coordinates[1]];
    }

    private function fetchRoute(array $departureCoordinates, array $destinationCoordinates): array
    {
        try {
            $response = $this->httpClient->request('POST', self::DIRECTIONS_ENDPOINT, [
                'headers' => [
                    'Authorization' => $this->openRouteServiceApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'coordinates' => [
                        $departureCoordinates,
                        $destinationCoordinates,
                    ],
                ],
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new RouteEstimationException('Unable to estimate the route at the moment.', 0, $exception);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RouteEstimationException('OpenRouteService directions returned an unexpected response.');
        }

        $feature = $data['features'][0] ?? null;

        if (!is_array($feature)) {
            throw new RouteEstimationException('No driving route could be found for this trip.');
        }

        $route = [
            'summary' => $feature['properties']['summary'] ?? null,
            'geometry' => $feature['geometry'] ?? null,
        ];

        return $route;
    }

    private function isValidGeoJsonLineString(mixed $geometry): bool
    {
        if (!is_array($geometry)) {
            return false;
        }

        if (($geometry['type'] ?? null) !== 'LineString') {
            return false;
        }

        $coordinates = $geometry['coordinates'] ?? null;

        return is_array($coordinates) && $coordinates !== [];
    }
}
