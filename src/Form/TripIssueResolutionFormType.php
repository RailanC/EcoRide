<?php

namespace App\Form;

use App\Entity\TripIssue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TripIssueResolutionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('resolution', ChoiceType::class, [
                'label' => 'Decision',
                'choices' => [
                    'Valider le trajet pour le conducteur' => TripIssue::STATUS_RESOLVED_FOR_DRIVER,
                    'Refuser le trajet pour le conducteur' => TripIssue::STATUS_RESOLVED_AGAINST_DRIVER,
                ],
                'expanded' => true,
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir une resolution.'),
                ],
            ])
            ->add('resolutionNote', TextareaType::class, [
                'label' => 'Note de resolution',
                'attr' => [
                    'rows' => 5,
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez ajouter une note de resolution.'),
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
