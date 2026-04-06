<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TripOutcomeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('outcome', ChoiceType::class, [
                'label' => 'Comment s est passe le trajet ?',
                'choices' => [
                    '5 etoiles - Excellent' => '5',
                    '4 etoiles - Bien passe' => '4',
                    '3 etoiles - Mitige' => '3',
                    '2 etoiles - Probleme important' => '2',
                    '1 etoile - Tres mauvais trajet' => '1',
                ],
                'expanded' => true,
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer votre note du trajet.'),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Ajoutez des details si un probleme est survenu.',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [
                new Callback([$this, 'validateComment']),
            ],
        ]);
    }

    public function validateComment(?array $data, ExecutionContextInterface $context): void
    {
        $score = (int) ($data['outcome'] ?? 0);

        if ($score > 0 && $score <= 3 && trim((string) ($data['comment'] ?? '')) === '') {
            $context->buildViolation('Veuillez decrire le probleme rencontre.')
                ->atPath('comment')
                ->addViolation();
        }
    }
}
