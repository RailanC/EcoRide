<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SearchPillFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldClass = 'form-control bg-transparent border-0 shadow-none p-0 text-white fw-medium';
        $labelAttr = [
            'class' => 'form-label text-uppercase fw-bold mb-1 home-search-label',
        ];

        $builder
            ->add('departure', TextType::class, [
                'label' => 'Départ',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => [
                    'class' => $fieldClass,
                    'placeholder' => 'Ville ou aéroport',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('arrival', TextType::class, [
                'label' => 'Destination',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => [
                    'class' => $fieldClass,
                    'placeholder' => 'Ville ou aéroport',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'label_attr' => $labelAttr,
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => $fieldClass,
                ],
            ])
            ->add('return_date', DateType::class, [
                'label' => 'Retour',
                'label_attr' => $labelAttr,
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => $fieldClass,
                    'disabled' => 'disabled',
                ],
            ])
            ->add('passengers', ChoiceType::class, [
                'label' => 'Voyageurs',
                'label_attr' => $labelAttr,
                'required' => false,
                'choices' => [
                    '1 passager' => 1,
                    '2 passagers' => 2,
                    '3 passagers' => 3,
                    '4 passagers' => 4,
                    '5+ passagers' => 5,
                ],
                'attr' => [
                    'class' => 'form-select bg-transparent border-0 shadow-none p-0 text-white fw-medium',
                ],
                'choice_attr' => static fn (): array => [
                    'class' => 'text-dark',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'd-flex flex-column flex-xl-row gap-2 gap-xl-0 align-items-stretch p-2',
            ],
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
