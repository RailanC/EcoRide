<?php

namespace App\Service;

final class RouteEstimate
{
    public function __construct(
        private readonly int $durationSeconds,
        private readonly int $distanceMeters,
        private readonly \DateTimeImmutable $estimatedArrival,
        private readonly array $geometry,
    ) {
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function getDistanceMeters(): int
    {
        return $this->distanceMeters;
    }

    public function getEstimatedArrival(): \DateTimeImmutable
    {
        return $this->estimatedArrival;
    }

    public function getEstimatedArrivalIso(): string
    {
        return $this->estimatedArrival->format(DATE_ATOM);
    }

    public function getGeometry(): array
    {
        return $this->geometry;
    }
}
