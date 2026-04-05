<?php

namespace App\Service;

use App\Entity\Booking;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BookingCancellationLinkSigner
{
    private const DEFAULT_EXPIRATION_INTERVAL = '+7 days';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function generateSignedCancellationUrl(Booking $booking): string
    {
        $bookingId = $booking->getId();

        if ($bookingId === null) {
            throw new \InvalidArgumentException('Impossible de generer un lien de suppression pour une reservation non persistée.');
        }

        $expiresAt = $this->resolveExpirationTimestamp($booking);
        $url = $this->urlGenerator->generate('app_booking_cancel_from_email', [
            'id' => $bookingId,
            'expires' => $expiresAt,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url);
    }

    public function validateSignedRequest(Request $request): bool
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return false;
        }

        $expires = $request->query->getInt('expires');

        return $expires > time();
    }

    private function resolveExpirationTimestamp(Booking $booking): int
    {
        $trip = $booking->getTrip();
        $departureDate = $trip?->getDepartureDate();
        $departureTime = $trip?->getDepartureTime();

        if ($departureDate instanceof \DateTimeInterface && $departureTime instanceof \DateTimeInterface) {
            $departureAt = new \DateTimeImmutable(sprintf(
                '%s %s',
                $departureDate->format('Y-m-d'),
                $departureTime->format('H:i:s')
            ));

            if ($departureAt->getTimestamp() > time()) {
                return $departureAt->getTimestamp();
            }
        }

        return (new \DateTimeImmutable(self::DEFAULT_EXPIRATION_INTERVAL))->getTimestamp();
    }
}
