<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Brand;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const DEFAULT_PASSWORD = 'password';
    private const BRAND_NAMES = [
        'Renault', 'Peugeot', 'Citroën', 'Toyota', 'Volkswagen', 'Ford', 'BMW', 'Mercedes',
        'Audi', 'Nissan', 'Hyundai', 'Kia', 'Tesla', 'Opel', 'Seat', 'Fiat', 'Mazda', 'Honda', 'Volvo', 'Mitsubishi',
    ];

    private const CITIES = [
        'Paris', 'Lyon', 'Marseille', 'Bordeaux', 'Nice', 'Toulouse', 'Lille', 'Nantes', 'Strasbourg', 'Rennes',
        'Montpellier', 'Grenoble', 'Dijon', 'Le Havre', 'Reims', 'Tours', 'Angers', 'Caen', 'Aix-en-Provence', 'Nancy',
    ];

    private const MODELS = [
        'Clio', '208', 'C3', 'Yaris', 'Golf', 'Focus', 'M3', 'C-Class', 'A3', 'Micra', 'i10', 'Rio', 'Model 3', 'Corsa',
        'Ibiza', '500', 'CX-5', 'Civic', 'XC60', 'Outlander',
    ];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $limit = static fn (string $value, int $length = 50): string => mb_substr($value, 0, $length);

        $brands = [];
        $users = [];
        $vehicles = [];
        $trips = [];

        foreach (self::BRAND_NAMES as $brandName) {
            $brand = new Brand();
            $brand->setLabel($brandName);
            $brands[] = $brand;
            $manager->persist($brand);
        }

        for ($i = 0; $i < 50; $i++) {
            $user = new User();
            $user->setFirstName($limit($faker->firstName()));
            $user->setLastName($limit($faker->lastName()));
            $user->setEmail(sprintf('user%d@ecoride.test', $i + 1));
            $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
            $user->setPhone($faker->phoneNumber());
            $user->setAddress($limit(str_replace("\n", ', ', $faker->address())));
            $user->setPhoto('https://picsum.photos/seed/' . ($i + 1) . '/200/200');
            $user->setUsername($limit($faker->unique()->userName()));
            $user->setCreditBalance(number_format($faker->randomFloat(2, 0, 500), 2, '.', ''));

            $type = $faker->randomElement(['passenger', 'driver', 'both']);
            $user->setType($type);

            if ($i % 10 === 0) {
                $user->setRoles(['ROLE_EMPLOYEUR', 'ROLE_USER']);
            } else {
                $user->setRoles(['ROLE_USER']);
            }

            $user->setIsVerified(true);
            $user->setBirthDate($faker->dateTimeBetween('-55 years', '-18 years'));

            if ($type === 'driver' || $type === 'both') {
                $vehicleCount = $faker->numberBetween(1, 2);
                for ($j = 0; $j < $vehicleCount; $j++) {
                    $vehicle = new Vehicle();
                    $vehicle->setBrand($faker->randomElement($brands));
                    $vehicle->setModel($faker->randomElement(self::MODELS));
                    $vehicle->setFirstRegistrationDate($faker->dateTimeBetween('2010-01-01', '2024-12-31'));
                    $vehicle->setColor($faker->randomElement(['Noir', 'Blanc', 'Rouge', 'Bleu', 'Gris', 'Vert']));
                    $vehicle->setRegistrationNumber($faker->bothify('??-###-??'));
                    $vehicle->setEnergyType($faker->randomElement(['gasoline', 'diesel', 'electric', 'hybrid']));
                    $vehicle->setPlaces($faker->numberBetween(2, 7));
                    $vehicle->setPreferences([
                        'smoking' => $faker->boolean() ? 1 : 0,
                        'animals' => $faker->boolean() ? 1 : 0,
                        'custom' => [],
                    ]);
                    $vehicle->setOwner($user);

                    $vehicles[] = $vehicle;
                    $manager->persist($vehicle);
                }
            }

            $users[] = $user;
            $manager->persist($user);
        }

        $now = new \DateTime('now');
        $tripCount = 120;
        for ($i = 0; $i < $tripCount; $i++) {
            $vehicle = $faker->randomElement($vehicles);
            $departure = (clone $now)->modify(sprintf('+%d days', $faker->numberBetween(1, 365)));
            $departure->setTime($faker->numberBetween(6, 22), $faker->numberBetween(0, 59), 0);

            $arrival = clone $departure;
            $arrival->modify(sprintf('+%d hours', $faker->numberBetween(1, 6)));

            $trip = new Trip();
            $trip->setAvailableSeats($faker->numberBetween(2, 4));
            $trip->setDepartureDate(clone $departure);
            $trip->setDepartureTime(clone $departure);
            $trip->setDepartureLocation($faker->randomElement(self::CITIES));
            $trip->setArrivalDate(clone $arrival);
            $trip->setArrivalTime(clone $arrival);
            $trip->setArrivalLocation($faker->randomElement(self::CITIES));
            $trip->setPricePerPerson(number_format($faker->randomFloat(2, 10, 60), 2, '.', ''));
            $trip->setVehicle($vehicle);
            $trip->setDriver($vehicle->getOwner());
            $trip->setStatus($faker->randomElement([
                Trip::STATUS_PLANNED,
                Trip::STATUS_COMPLETED,
                Trip::STATUS_CANCELED,
            ]));

            $trips[] = $trip;
            $manager->persist($trip);
        }

        foreach ($trips as $trip) {
            $passengers = array_values(array_filter(
                $users,
                static fn (User $user): bool => $user !== $trip->getDriver()
            ));

            if ($passengers === []) {
                continue;
            }

            $bookingCount = $faker->numberBetween(0, min(3, count($passengers)));
            for ($i = 0; $i < $bookingCount; $i++) {
                $booking = new Booking();
                $booking->setConfirmation($faker->boolean(80));
                $booking->setCreditsUsed($faker->numberBetween(0, 40));
                $booking->setStatus($faker->randomElement([
                    Booking::STATUS_CONFIRMED,
                    Booking::STATUS_REFUNDED,
                    Booking::STATUS_CANCELED,
                ]));
                $booking->setOutcomeStatus($faker->randomElement([
                    Booking::OUTCOME_PENDING,
                    Booking::OUTCOME_CONFIRMED_GOOD,
                    Booking::OUTCOME_REPORTED_PROBLEM,
                ]));
                $booking->setTrip($trip);
                $booking->setUser($faker->randomElement($passengers));

                $manager->persist($booking);
            }
        }

        $manager->flush();
    }
}
