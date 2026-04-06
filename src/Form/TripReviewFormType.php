<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TripReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Note',
                'choices' => [
                    '5/5' => 5,
                    '4/5' => 4,
                    '3/5' => 3,
                    '2/5' => 2,
                    '1/5' => 1,
                ],
                'expanded' => true,
                'constraints' => [
                    new NotBlank(message: 'Veuillez attribuer une note.'),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Avis',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Partagez votre experience du trajet.',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un avis.'),
                    new Length(max: 1000, maxMessage: 'Votre avis ne peut pas depasser {{ limit }} caracteres.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
