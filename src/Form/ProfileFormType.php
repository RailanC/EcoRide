<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'label_attr' => ['class' => 'contact-label'],
                'required' => true,
                'attr' => [
                    'autocomplete' => 'given-name',
                    'class' => 'form-control contact-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(
                        min: 2,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        max: 50,
                        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'label_attr' => ['class' => 'contact-label'],
                'required' => true,
                'attr' => [
                    'autocomplete' => 'family-name',
                    'class' => 'form-control contact-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(
                        min: 2,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        max: 50,
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => 'votre@email.com',
                    'style' => 'padding-left:2.5rem;',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'L\'e-mail est obligatoire.'),
                    new Email(message: 'Veuillez saisir un e-mail valide.'),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'label_attr' => ['class' => 'contact-label'],
                'empty_data' => '',
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => '+33 6 00 00 00 00',
                    'autocomplete' => 'tel',
                    'style' => 'padding-left:2.5rem;',
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 20,
                        maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'label_attr' => ['class' => 'contact-label'],
                'empty_data' => '',
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => '123 Rue Exemple, 75000 Paris',
                    'autocomplete' => 'street-address',
                    'style' => 'padding-left:2.5rem;',
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 255,
                        maxMessage: 'L’adresse ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'À propos de moi',
                'label_attr' => ['class' => 'contact-label'],
                'attr' => [
                    'class' => 'form-control contact-input',
                    'placeholder' => 'Parlez un peu de vous…',
                    'rows' => 3,
                    'style' => 'resize:none;',
                ],
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 500,
                        maxMessage: 'La biographie ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'label_attr' => ['class' => 'contact-label'],
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '••••••••',
                    'autocomplete' => 'current-password',
                    'class' => 'form-control contact-input',
                    'style' => 'padding-right:2.75rem;',
                    'id' => 'currentPassword',
                ],
                'constraints' => [
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'label_attr' => ['class' => 'contact-label'],
                    'attr' => [
                        'placeholder' => '••••••••',
                        'autocomplete' => 'new-password',
                        'class' => 'form-control contact-input',
                        'style' => 'padding-right:2.75rem;',
                        'id' => 'newPassword',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe',
                    'label_attr' => ['class' => 'contact-label'],
                    'attr' => [
                        'placeholder' => '••••••••',
                        'autocomplete' => 'new-password',
                        'class' => 'form-control contact-input',
                        'style' => 'padding-right:2.75rem;',
                        'id' => 'confirmPassword',
                    ],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'constraints' => [
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('vehicles', CollectionType::class, [
                'entry_type' => VehicleFormType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'attr' => [
                    'class' => 'vehicle-collection',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'allow_extra_fields' => true,
        ]);
    }
}
