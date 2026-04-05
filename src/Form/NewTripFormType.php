<?php

namespace App\Form;

use App\Entity\Trip;
use App\Entity\Vehicle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;

class NewTripFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hasSingleVehicle = count($options['vehicles']) === 1;

        $builder
            ->add('departure_city', TextType::class, [
                'property_path' => 'departureLocation',
                'label' => 'Ville de depart',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'id' => 'departure_city',
                    'name' => 'departure_city',
                    'class' => 'form-control contact-input',
                    'placeholder' => 'Ex. Paris',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La ville de depart est obligatoire.'),
                    new Length(
                        max: 50,
                        maxMessage: 'La ville de depart ne peut pas depasser {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('arrival_city', TextType::class, [
                'property_path' => 'arrivalLocation',
                'label' => 'Ville d\'arrivee',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'id' => 'arrival_city',
                    'name' => 'arrival_city',
                    'class' => 'form-control contact-input',
                    'placeholder' => 'Ex. Lyon',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La ville d\'arrivee est obligatoire.'),
                    new Length(
                        max: 50,
                        maxMessage: 'La ville d\'arrivee ne peut pas depasser {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('departure_date', DateType::class, [
                'property_path' => 'departureDate',
                'label' => 'Date de depart',
                'label_attr' => ['class' => 'contact-label'],
                'widget' => 'single_text',
                'input' => 'datetime',
                'invalid_message' => 'Veuillez saisir une date de depart valide.',
                'attr' => [
                    'id' => 'departure_date',
                    'name' => 'departure_date',
                    'class' => 'form-control contact-input',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La date de depart est obligatoire.'),
                ],
            ])
            ->add('departure_time', TimeType::class, [
                'property_path' => 'departureTime',
                'label' => 'Heure de depart',
                'label_attr' => ['class' => 'contact-label'],
                'widget' => 'single_text',
                'input' => 'datetime',
                'invalid_message' => 'Veuillez saisir une heure de depart valide.',
                'attr' => [
                    'id' => 'departure_time',
                    'name' => 'departure_time',
                    'class' => 'form-control contact-input',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'L\'heure de depart est obligatoire.'),
                ],
            ])
            ->add('price', MoneyType::class, [
                'property_path' => 'pricePerPerson',
                'label' => 'Prix (credits)',
                'label_attr' => ['class' => 'contact-label'],
                'currency' => false,
                'divisor' => 1,
                'invalid_message' => 'Veuillez saisir un prix valide.',
                'attr' => [
                    'id' => 'price',
                    'name' => 'price',
                    'class' => 'form-control contact-input',
                    'min' => 0,
                    'max' => 100,
                    'placeholder' => 'Ex. 15',
                    'step' => '0.01',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le prix est obligatoire.'),
                    new PositiveOrZero(message: 'Le prix doit etre superieur ou egal a 0.'),
                    new Range(
                        min: 0,
                        max: 100,
                        notInRangeMessage: 'Le prix doit etre compris entre {{ min }} et {{ max }}.',
                    ),
                ],
            ])
            ->add('seats', HiddenType::class, [
                'property_path' => 'availableSeats',
                'label' => 'Places disponibles',
                'attr' => [
                    'id' => 'seats',
                    'name' => 'seats',
                    'data-seat-source' => 'hidden',
                ],
                'required' => false,
            ])
            ->add('car', EntityType::class, [
                'property_path' => 'vehicle',
                'class' => Vehicle::class,
                'choices' => $options['vehicles'],
                'choice_label' => static function (Vehicle $vehicle): string {
                    return sprintf(
                        '%s %s (%s)',
                        $vehicle->getBrand()?->getLabel() ?? '',
                        $vehicle->getModel(),
                        $vehicle->getEnergyType()
                    );
                },
                'placeholder' => $hasSingleVehicle ? null : 'Choisir une voiture',
                'label' => 'Voiture utilisee',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'id' => 'car',
                    'name' => 'car',
                    'class' => 'form-select contact-input',
                ],
                'disabled' => $hasSingleVehicle,
                'choice_attr' => static function (Vehicle $vehicle): array {
                    return [
                        'data-capacity' => (string) ($vehicle->getPlaces() ?? 0),
                    ];
                },
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Vous devez choisir une voiture.'),
                ],
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, $this->applyDefaultSeats(...));
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->validateTripRules(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trip::class,
            'vehicles' => [],
        ]);

        $resolver->setAllowedTypes('vehicles', 'array');
    }

    private function applyDefaultSeats(FormEvent $event): void
    {
        $trip = $event->getData();

        if (!$trip instanceof Trip) {
            return;
        }

        $vehicle = $trip->getVehicle();

        if (!$vehicle instanceof Vehicle) {
            return;
        }

        $capacity = (int) ($vehicle->getPlaces() ?? 0);

        if ($capacity <= 1) {
            return;
        }

        if ($trip->getAvailableSeats() === null) {
            $trip->setAvailableSeats($capacity - 1);
        }
    }

    private function validateTripRules(FormEvent $event): void
    {
        $trip = $event->getData();
        $form = $event->getForm();

        if (!$trip instanceof Trip) {
            return;
        }

        $vehicle = $trip->getVehicle();

        if ($vehicle instanceof Vehicle) {
            $capacity = (int) ($vehicle->getPlaces() ?? 0);
            $availableSeats = $trip->getAvailableSeats();

            if ($capacity <= 1) {
                $form->get('car')->addError(new FormError('La voiture selectionnee ne permet pas de proposer des places passagers.'));
            } elseif ($availableSeats === null) {
                $form->get('seats')->addError(new FormError('Le nombre de places disponibles est invalide.'));
            } else {
                $defaultAvailableSeats = $capacity - 1;
                $maxCustomSeats = $capacity - 2;

                if ($availableSeats < 1) {
                    $form->get('seats')->addError(new FormError('Le nombre de places disponibles doit etre au moins egal a 1.'));
                }

                if ($availableSeats > $defaultAvailableSeats) {
                    $form->get('seats')->addError(new FormError(sprintf(
                        'Le nombre de places disponibles ne peut pas depasser %d pour cette voiture.',
                        $defaultAvailableSeats
                    )));
                }

                if ($availableSeats !== $defaultAvailableSeats && $availableSeats > $maxCustomSeats) {
                    $form->get('seats')->addError(new FormError(sprintf(
                        'Si vous reduisez les places, vous devez choisir une valeur comprise entre 1 et %d.',
                        max(1, $maxCustomSeats)
                    )));
                }
            }
        }

        if (!$trip->getDepartureDate() instanceof \DateTimeInterface || !$trip->getDepartureTime() instanceof \DateTimeInterface) {
            return;
        }

        $departureDateTime = new \DateTimeImmutable(sprintf(
            '%s %s',
            $trip->getDepartureDate()->format('Y-m-d'),
            $trip->getDepartureTime()->format('H:i:s')
        ));
        $minimumDeparture = new \DateTimeImmutable('+24 hours');

        if ($departureDateTime < $minimumDeparture) {
            $form->get('departure_time')->addError(
                new FormError('Le depart doit etre programme au moins 24 heures a l\'avance.')
            );
        }
    }
}
