<?php

namespace App\Entity;

use App\Repository\TripIssueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripIssueRepository::class)]
#[ORM\Table(name: 'trip_issues')]
class TripIssue
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED_FOR_DRIVER = 'resolved_for_driver';
    public const STATUS_RESOLVED_AGAINST_DRIVER = 'resolved_against_driver';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'trip_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Trip $trip = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'participant_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $participant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'driver_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $driver = null;

    #[ORM\Column(length: 1000)]
    private ?string $comment = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_OPEN;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $resolutionNote = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'resolved_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getParticipant(): ?User
    {
        return $this->participant;
    }

    public function setParticipant(?User $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

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

    public function getResolutionNote(): ?string
    {
        return $this->resolutionNote;
    }

    public function setResolutionNote(?string $resolutionNote): static
    {
        $this->resolutionNote = $resolutionNote;

        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?User $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeInterface $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
