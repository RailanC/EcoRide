<?php

namespace App\Tests\Service;

use App\Exception\RouteEstimationException;
use App\Service\TripRouteEstimator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TripRouteEstimatorTest extends TestCase
{
    public function testEstimateReturnsDurationDistanceAndArrival(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [2.3522, 48.8566],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [4.8357, 45.7640],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'features' => [
                    [
                        'properties' => [
                            'summary' => [
                                'duration' => 7200,
                                'distance' => 465000,
                            ],
                        ],
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => [
                                [2.3522, 48.8566],
                                [4.8357, 45.7640],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $estimator = new TripRouteEstimator($httpClient, 'test-key');
        $departure = new \DateTimeImmutable('2026-04-08 10:00:00');

        $estimate = $estimator->estimate('Paris', 'Lyon', $departure);

        self::assertSame(7200, $estimate->getDurationSeconds());
        self::assertSame(465000, $estimate->getDistanceMeters());
        self::assertSame('LineString', $estimate->getGeometry()['type'] ?? null);
        self::assertSame(
            $departure->modify('+7200 seconds')?->getTimestamp(),
            $estimate->getEstimatedArrival()->getTimestamp()
        );
    }

    public function testEstimateFailsWhenGeocodingReturnsNoCoordinates(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'features' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $estimator = new TripRouteEstimator($httpClient, 'test-key');

        $this->expectException(RouteEstimationException::class);
        $this->expectExceptionMessage('Unable to find coordinates for "Paris".');

        $estimator->estimate('Paris', 'Lyon', new \DateTimeImmutable('2026-04-08 10:00:00'));
    }

    public function testEstimateFailsWhenDirectionsReturnNoRoute(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [2.3522, 48.8566],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [4.8357, 45.7640],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'features' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $estimator = new TripRouteEstimator($httpClient, 'test-key');

        $this->expectException(RouteEstimationException::class);
        $this->expectExceptionMessage('No driving route could be found for this trip.');

        $estimator->estimate('Paris', 'Lyon', new \DateTimeImmutable('2026-04-08 10:00:00'));
    }

    public function testEstimateFailsWhenApiKeyIsMissing(): void
    {
        $estimator = new TripRouteEstimator(new MockHttpClient(), '');

        $this->expectException(RouteEstimationException::class);
        $this->expectExceptionMessage('OpenRouteService API key is not configured.');

        $estimator->estimate('Paris', 'Lyon', new \DateTimeImmutable('2026-04-08 10:00:00'));
    }
}
