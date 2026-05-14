<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Vehicle;
use App\Entity\Trip;
use App\Entity\Booking;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const DEFAULT_PASSWORD = 'password';

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

        // Create 100 brands with random data
        for ($i = 0; $i < 100; $i++) {
            $brand = new Brand();
            $brand->setLabel($limit($faker->company()));

            $brands[] = $brand;
            $manager->persist($brand);
        }

        // Create 100 users with random data
        for ($i = 0; $i < 100; $i++) {
            $user = new User();
            $user->setFirstName($limit($faker->firstName()));
            $user->setLastName($limit($faker->lastName()));
            $user->setEmail($limit($faker->unique()->safeEmail()));
            $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
            $user->setPhone($faker->phoneNumber());
            $user->setAddress($limit(str_replace("\n", ', ', $faker->address())));
            $user->setPhoto($faker->imageUrl(200, 200, 'people'));
            $user->setUsername($limit($faker->unique()->userName()));
            $user->setCreditBalance(number_format($faker->randomFloat(2, 0, 1000), 2, '.', ''));
            $user->setType($faker->randomElement(['passenger', 'driver', 'both']));

            if ($i % 10 === 0) {
                $user->setRoles(['ROLE_EMPLOYEUR']);
            } else {
                $user->setRoles(['ROLE_USER']);
            }

            $user->setIsVerified(true);
            $user->setBirthDate($faker->dateTimeBetween('-30 years', '-18 years'));

            // Create vehicles for each user
            if ($user->getType() === 'driver' || $user->getType() === 'both') {
                for ($j = 0; $j < rand(1, 3); $j++) {
                    $vehicle = new Vehicle();
                    $vehicle->setBrand($faker->randomElement($brands));
                    $vehicle->setModel($limit($faker->word()));
                    $vehicle->setFirstRegistrationDate($faker->dateTimeBetween('1990-01-01', '2022-12-31'));
                    $vehicle->setColor($limit($faker->safeColorName()));
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

            // Persist the user
            $manager->persist($user);
        }

        // Create trips
        for ($l = 0; $l < 100; $l++) {
            $vehicle = $faker->randomElement($vehicles);
            $departureDate = $faker->dateTimeBetween('now', '+1 month');
            $arrivalDate = (clone $departureDate)->modify(sprintf('+%d hours', $faker->numberBetween(1, 8)));

            $trip = new Trip();
            $trip->setAvailableSeats($faker->numberBetween(1, 5));
            $trip->setDepartureDate($departureDate);
            $trip->setDepartureTime($departureDate);
            $trip->setDepartureLocation($limit($faker->city()));
            $trip->setArrivalDate($arrivalDate);
            $trip->setArrivalTime($arrivalDate);
            $trip->setStatus($faker->randomElement([
                Trip::STATUS_PLANNED,
                Trip::STATUS_COMPLETED,
                Trip::STATUS_CANCELED,
            ]));
            $trip->setVehicle($vehicle);
            $trip->setArrivalLocation($limit($faker->city()));
            $trip->setPricePerPerson(number_format($faker->randomFloat(2, 10, 100), 2, '.', ''));
            $trip->setDriver($vehicle->getOwner());

            $trips[] = $trip;
            $manager->persist($trip);
        }

        // Create bookings
        for ($k = 0; $k < 100; $k++) {
            $trip = $faker->randomElement($trips);
            $passengers = array_values(array_filter(
                $users,
                static fn (User $user): bool => $user !== $trip->getDriver()
            ));

            $booking = new Booking();
            $booking->setConfirmation($faker->boolean());
            $booking->setCreditsUsed($faker->numberBetween(1, 100));
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

        $manager->flush();
    }
}
