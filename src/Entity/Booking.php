<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
class Booking
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELED = 'canceled';

    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_CONFIRMED_GOOD = 'confirmed_good';
    public const OUTCOME_REPORTED_PROBLEM = 'reported_problem';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $confirmation = null;

    #[ORM\Column]
    private ?int $creditsUsed = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 50)]
    private ?string $outcomeStatus = self::OUTCOME_PENDING;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $respondedAt = null;

    #[ORM\Column]
    private bool $payoutApplied = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $driverCreditAmount = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'trip_id', referencedColumnName: 'id', nullable: true)]
    private ?Trip $trip = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isConfirmation(): ?bool
    {
        return $this->confirmation;
    }

    public function setConfirmation(bool $confirmation): static
    {
        $this->confirmation = $confirmation;

        return $this;
    }

    public function getCreditsUsed(): ?int
    {
        return $this->creditsUsed;
    }

    public function setCreditsUsed(int $creditsUsed): static
    {
        $this->creditsUsed = $creditsUsed;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function getOutcomeStatus(): ?string
    {
        return $this->outcomeStatus;
    }

    public function setOutcomeStatus(string $outcomeStatus): static
    {
        $this->outcomeStatus = $outcomeStatus;

        return $this;
    }

    public function getRespondedAt(): ?\DateTimeInterface
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeInterface $respondedAt): static
    {
        $this->respondedAt = $respondedAt;

        return $this;
    }

    public function isPayoutApplied(): bool
    {
        return $this->payoutApplied;
    }

    public function setPayoutApplied(bool $payoutApplied): static
    {
        $this->payoutApplied = $payoutApplied;

        return $this;
    }

    public function getDriverCreditAmount(): ?string
    {
        return $this->driverCreditAmount;
    }

    public function setDriverCreditAmount(?string $driverCreditAmount): static
    {
        $this->driverCreditAmount = $driverCreditAmount;

        return $this;
    }
}
