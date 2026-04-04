<?php

namespace App\Form;

use App\Entity\Brand;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class VehicleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('registrationNumber', TextType::class, [
                'label' => 'Plaque d\'immatriculation',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => 'AB-123-CD',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La plaque d\'immatriculation est obligatoire.'),
                    new Length(
                        min: 3,
                        max: 50,
                        minMessage: 'La plaque doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'La plaque ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('brand', EntityType::class, [
                'class' => Brand::class,
                'choice_label' => 'label',
                'choice_value' => 'label',
                'label' => 'Marque',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'class' => 'form-select contact-input',
                    'style' => 'appearance:none;padding-right:2.5rem;',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La marque est obligatoire.'),
                ],
                'query_builder' => static function (EntityRepository $entityRepository) {
                    return $entityRepository->createQueryBuilder('brand')
                        ->orderBy('brand.label', 'ASC');
                },
            ])
            ->add('model', TextType::class, [
                'label' => 'Modèle',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => 'Zoé',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le modèle est obligatoire.'),
                    new Length(
                        min: 1,
                        max: 50,
                        minMessage: 'Le modèle doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le modèle ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('color', TextType::class, [
                'label' => 'Couleur',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => 'Blanche',
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 50,
                        maxMessage: 'La couleur ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('energyType', ChoiceType::class, [
                'label' => 'Type d\'énergie',
                'label_attr' => ['class' => 'contact-label'],
                'choices' => [
                    'Essence' => 'Essence',
                    'Diesel' => 'Diesel',
                    'Électrique' => 'Électrique',
                    'Hybride' => 'Hybride',
                    'Hybride rechargeable' => 'Hybride rechargeable',
                    'GNV' => 'GNV',
                ],
                'attr' => [
                    'class' => 'form-select contact-input',
                    'style' => 'appearance:none;padding-right:2.5rem;',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le type d\'énergie est obligatoire.'),
                ],
            ])
            ->add('firstRegistrationDate', DateType::class, [
                'label' => '1ère mise en circulation',
                'label_attr' => ['class' => 'contact-label'],
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control contact-input',
                    'style' => 'color-scheme:dark;',
                ],
                'required' => false,
            ])
            ->add('places', ChoiceType::class, [
                'label' => 'Places disponibles',
                'label_attr' => ['class' => 'contact-label'],
                'choices' => [
                    '1 place' => 1,
                    '2 places' => 2,
                    '3 places' => 3,
                    '4 places' => 4,
                    '5 places' => 5,
                    '6 places' => 6,
                    '7 places' => 7,
                ],
                'attr' => [
                    'class' => 'form-select contact-input',
                    'style' => 'appearance:none;padding-right:2.5rem;',
                ],
                'required' => false,
            ])
            ->add('preferences', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'data-preferences-hidden' => true,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicle::class,
        ]);
    }
}
