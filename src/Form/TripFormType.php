<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TripFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('energyType', ChoiceType::class, [
                'label' => 'Energie',
                'required' => false,
                'expanded' => true,
                'choices' => [
                    'Tous Types' => '',
                    'Essence' => 'essence',
                    'Diesel' => 'diesel',
                    'Electric' => 'electric',
                    'Hybrid' => 'hybrid',
                    'GNV' => 'gnv',
                ],
            ])
            ->add('max_price', RangeType::class, [
                'label' => 'Prix max (EUR)',
                'required' => false,
                'attr' => [
                    'class' => 'trip-search-input',
                    'min' => 10,
                    'max' => 100,
                    'step' => 5,
                ],
            ])
            ->add('checkRating', CheckboxType::class, [
                'label' => 'Filtrer par note minimale de Covoitureur ?',
                'required' => false,
                'attr' => [
                    'id' => 'check_rating',
                ],
                'label_attr' => [
                    'class' => 'trip-search-input',
                ],
            ])
            ->add('min_rating', RangeType::class, [
                'label' => 'Note minimale de Covoitureur',
                'required' => false,
                'attr' => [
                    'class' => 'trip-search-input',
                    'min' => 1,
                    'max' => 5,
                    'step' => 1,
                ],
            ])
            ->add('departure_time', ChoiceType::class, [
                'label' => 'Heure de depart',
                'required' => false,
                'placeholder' => 'Toutes',
                'choices' => [
                    'Matin (06:00 - 12:00)' => 'morning',
                    'Apres-midi (12:00 - 18:00)' => 'afternoon',
                    'Soir (18:00 - 23:00)' => 'evening',
                ],
                'attr' => [
                    'class' => 'trip-search-input shadow-none',
                ],
            ])
            ->add('seats_available', ChoiceType::class, [
                'label' => 'Places disponibles',
                'required' => false,
                'placeholder' => 'Toutes',
                'choices' => [
                    '1 place' => 1,
                    '2 places' => 2,
                    '3 places' => 3,
                    '4 places' => 4,
                ],
                'attr' => [
                    'class' => 'trip-search-input shadow-none',
                ],
            ])
            ->add('departure', TextType::class, [
                'label' => 'Depart',
                'required' => false,
                'attr' => [
                    'class' => 'trip-search-input',
                    'placeholder' => 'Paris, FR',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('arrival', TextType::class, [
                'label' => 'Destination',
                'required' => false,
                'attr' => [
                    'class' => 'trip-search-input',
                    'placeholder' => 'Destination',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('date', TextType::class, [
                'label' => 'Aller',
                'required' => false,
                'attr' => [
                    'class' => 'trip-search-input',
                    'type' => 'date',
                ],
            ])
            ->add('return_date', TextType::class, [
                'label' => 'Retour',
                'required' => false,
                'attr' => [
                    'class' => 'trip-search-input',
                    'type' => 'date',
                ],
            ])
            ->add('trip_type', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'id' => 'trip_type',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'data_class' => null,
            'method' => 'GET',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
