<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, name: 'last_name')]
    private ?string $lastName = null;

    #[ORM\Column(length: 50, name: 'first_name')]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 50)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $birthDate = null;

    #[ORM\Column(type: Types::BLOB)]
    private mixed $photo = null;

    #[ORM\Column(length: 50)]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user')]
    private Collection $bookings;

    /**
     * @var Collection<int, Vehicle>
     */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'owner', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $vehicles;

    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'driver')]
    private Collection $trips;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'user')]
    private Collection $reviews;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, name: 'credit_balance')]
    private ?string $creditBalance = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->vehicles = new ArrayCollection();
        $this->trips = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getBirthDate(): ?\DateTime
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTime $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getPhoto(): mixed
    {
        return $this->photo;
    }

    public function setPhoto(mixed $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setUser($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking) && $booking->getUser() === $this) {
            $booking->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): static
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles->add($vehicle);
            $vehicle->setOwner($this);
        }

        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): static
    {
        if ($this->vehicles->removeElement($vehicle) && $vehicle->getOwner() === $this) {
            $vehicle->setOwner(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): static
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setDriver($this);
        }

        return $this;
    }

    public function removeTrip(Trip $trip): static
    {
        if ($this->trips->removeElement($trip) && $trip->getDriver() === $this) {
            $trip->setDriver(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setUser($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review) && $review->getUser() === $this) {
            $review->setUser(null);
        }

        return $this;
    }

    public function getAverageValidatedRating(): ?float
    {
        $validatedReviews = array_filter(
            $this->reviews->toArray(),
            static fn (Review $review): bool => $review->getStatus() === Review::STATUS_APPROVED
        );

        if ($validatedReviews === []) {
            return null;
        }

        $total = array_reduce(
            $validatedReviews,
            static fn (int|float $carry, Review $review): int|float => $carry + (float) $review->getRating(),
            0
        );

        return round($total / count($validatedReviews), 1);
    }

    public function getValidatedReviewCount(): int
    {
        return count(array_filter(
            $this->reviews->toArray(),
            static fn (Review $review): bool => $review->getStatus() === Review::STATUS_APPROVED
        ));
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function eraseCredentials(): void
    {
        // Clear temporary sensitive data if needed.
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getCreditBalance(): ?string
    {
        return $this->creditBalance;
    }

    public function setCreditBalance(string $creditBalance): static
    {
        $this->creditBalance = $creditBalance;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
}
