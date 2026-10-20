<?php

namespace App\Form;

use App\Entity\EditionSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EditionSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disabled = (bool) $options['locked'];

        $builder
            ->add('budgetMax', NumberType::class, [
                'label' => 'Budget max (€)',
                'scale' => 2,
                'help' => 'Budget conseillé par souhait (un dépassement reste possible).',
                'disabled' => $disabled,
                'attr' => ['min' => 1, 'step' => '0.01', 'inputmode' => 'decimal'],
            ])
            ->add('eventDate', DateType::class, [
                'label' => 'Date de l’événement',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
                'help' => 'Affichée aux participants (optionnel).',
                'disabled' => $disabled,
            ])
            ->add('drawDate', DateType::class, [
                'label' => 'Date du tirage',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
                'help' => 'Informationnelle uniquement — n’exécute pas le tirage automatiquement.',
                'disabled' => $disabled,
            ])
            ->add('welcomeEmailTemplate', TextareaType::class, [
                'label' => 'Email de bienvenue',
                'attr' => ['rows' => 5],
                'disabled' => $disabled,
            ])
            ->add('resultEmailTemplate', TextareaType::class, [
                'label' => 'Email de résultat du tirage',
                'attr' => ['rows' => 5],
                'disabled' => $disabled,
            ])
            ->add('reminderEmailTemplate', TextareaType::class, [
                'label' => 'Email de rappel souhaits',
                'attr' => ['rows' => 5],
                'disabled' => $disabled,
            ])
            ->add('easterEggEnabled', CheckboxType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-switch',
                    'aria-label' => 'Activer le mini-jeu Le lutin farceur',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditionSettings::class,
            'locked' => false,
        ]);
        $resolver->setAllowedTypes('locked', 'bool');
    }
}
