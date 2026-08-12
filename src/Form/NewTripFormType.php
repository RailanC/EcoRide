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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;
use DateTimeImmutable;
use DateTimeInterface;

class NewTripFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hasSingleVehicle = count($options['vehicles']) === 1;

        $builder
            ->add('departure_city', TextType::class, $this->createTextOptions(
                'departureLocation',
                'Ville de départ',
                'D\'où partez-vous ?'
            ))
            ->add('arrival_city', TextType::class, $this->createTextOptions(
                'arrivalLocation',
                'Ville d\'arrivée',
                'Ex. Lyon'
            ))
            ->add('departure_date', DateType::class, $this->createDateOptions(
                'departureDate',
                'Date de départ',
                'departure_date',
                'Veuillez saisir une date de départ valide.'
            ))
            ->add('departure_time', TimeType::class, $this->createTimeOptions(
                'departureTime',
                'Heure de départ',
                'departure_time',
                'Veuillez saisir une heure de départ valide.'
            ))
            ->add('price_per_passenger', MoneyType::class, $this->createPriceOptions())
            ->add('seats', HiddenType::class, [
                'property_path' => 'availableSeats',
                'attr' => [
                    'id' => 'form_seats',
                    'data-seat-source' => 'hidden',
                ],
                'required' => false,
            ])
            ->add('car', EntityType::class, [
                'property_path' => 'vehicle',
                'class' => Vehicle::class,
                'choices' => $options['vehicles'],
                'choice_value' => static fn(?Vehicle $vehicle): ?string => $vehicle ? (string) $vehicle->getId() : null,
                'choice_label' => $this->createVehicleChoiceLabel(...),
                'choice_attr' => $this->createVehicleChoiceAttr(...),
                'expanded' => true,
                'multiple' => false,
                'label' => 'Voiture utilisee',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => ['class' => 'vehicle-selector'],
                'disabled' => $hasSingleVehicle,
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

    private function createTextOptions(string $propertyPath, string $label, string $placeholder): array
    {
        return [
            'property_path' => $propertyPath,
            'label' => $label,
            'label_attr' => ['class' => 'contact-label'],
            'attr' => [
                'class' => 'form-field__input',
                'placeholder' => $placeholder,
            ],
            'required' => true,
            'constraints' => [
                new NotBlank(message: sprintf('Le champ %s est obligatoire.', mb_strtolower($label))),
                new Length(
                    max: 50,
                    maxMessage: 'Le champ ne peut pas depasser {{ limit }} caracteres.',
                ),
            ],
        ];
    }

    private function createDateOptions(string $propertyPath, string $label, string $id, string $invalidMessage): array
    {
        return [
            'property_path' => $propertyPath,
            'label' => $label,
            'label_attr' => ['class' => 'contact-label'],
            'widget' => 'single_text',
            'input' => 'datetime',
            'invalid_message' => $invalidMessage,
            'attr' => [
                'id' => $id,
                'class' => 'form-field__input',
            ],
            'required' => true,
            'constraints' => [
                new NotBlank(message: sprintf('Le champ %s est obligatoire.', mb_strtolower($label))),
            ],
        ];
    }

    private function createTimeOptions(string $propertyPath, string $label, string $id, string $invalidMessage): array
    {
        return [
            'property_path' => $propertyPath,
            'label' => $label,
            'label_attr' => ['class' => 'contact-label'],
            'widget' => 'single_text',
            'input' => 'datetime',
            'invalid_message' => $invalidMessage,
            'attr' => [
                'id' => $id,
                'class' => 'form-field__input',
            ],
            'required' => true,
            'constraints' => [
                new NotBlank(message: sprintf('Le champ %s est obligatoire.', mb_strtolower($label))),
            ],
        ];
    }

    private function createPriceOptions(): array
    {
        return [
            'property_path' => 'pricePerPerson',
            'label' => 'Prix (crédits)',
            'label_attr' => ['class' => 'contact-label'],
            'currency' => false,
            'divisor' => 1,
            'invalid_message' => 'Veuillez saisir un prix valide.',
            'attr' => [
                'class' => 'form-field__input-wrapper form-field__input-wrapper--suffixed form-field__input',
                'min' => 0,
                'max' => 100,
                'placeholder' => 'Ex. 15',
                'step' => '0.01',
            ],
            'required' => true,
            'constraints' => [
                new NotBlank(message: 'Le prix est obligatoire.'),
                new PositiveOrZero(message: 'Le prix doit être supérieur ou égal à 0.'),
                new Range(
                    min: 0,
                    max: 100,
                    notInRangeMessage: 'Le prix doit être compris entre {{ min }} et {{ max }}.',
                ),
            ],
        ];
    }

    private function createVehicleChoiceLabel(Vehicle $vehicle): string
    {
        return sprintf(
            '%s %s (%s)',
            $vehicle->getBrand()?->getLabel() ?? '',
            $vehicle->getModel(),
            $vehicle->getEnergyType()
        );
    }

    private function createVehicleChoiceAttr(Vehicle $vehicle): array
    {
        return [
            'data-capacity' => (string) ($vehicle->getPlaces() ?? 0),
        ];
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
        if ($capacity <= 1 || $trip->getAvailableSeats() !== null) {
            return;
        }

        $trip->setAvailableSeats($capacity - 1);
    }

    private function validateTripRules(FormEvent $event): void
    {
        $trip = $event->getData();
        $form = $event->getForm();

        if (!$trip instanceof Trip) {
            return;
        }

        $this->validateVehicleCapacity($trip, $form);
        $this->validateDepartureDate($trip, $form);
    }

    private function validateVehicleCapacity(Trip $trip, FormInterface $form): void
    {
        $vehicle = $trip->getVehicle();
        if (!$vehicle instanceof Vehicle) {
            return;
        }

        $capacity = (int) ($vehicle->getPlaces() ?? 0);
        $availableSeats = $trip->getAvailableSeats();

        if ($capacity <= 1) {
            $form->get('car')->addError(new FormError('La voiture sélectionnée ne permet pas de proposer des places passagers.'));
            return;
        }

        if ($availableSeats === null) {
            $form->get('seats')->addError(new FormError('Le nombre de places disponibles est invalide.'));
            return;
        }

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

    private function validateDepartureDate(Trip $trip, FormInterface $form): void
    {
        if (!$trip->getDepartureDate() instanceof DateTimeInterface || !$trip->getDepartureTime() instanceof DateTimeInterface) {
            return;
        }

        $departureDateTime = new DateTimeImmutable(sprintf(
            '%s %s',
            $trip->getDepartureDate()->format('Y-m-d'),
            $trip->getDepartureTime()->format('H:i:s')
        ));

        if ($departureDateTime < new DateTimeImmutable('+24 hours')) {
            $form->get('departure_time')->addError(
                new FormError('Le depart doit etre programme au moins 24 heures a l\'avance.')
            );
        }
    }
}
