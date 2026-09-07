<?php

namespace App\Form;

use App\Entity\Exclusion;
use App\Entity\Participant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ExclusionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('source', EntityType::class, [
                'class' => Participant::class,
                'choice_label' => 'name',
                'label' => 'Cette personne',
                'placeholder' => 'Choisir…',
                'help' => 'Ne doit pas offrir de cadeau à…',
                'constraints' => [new Assert\NotBlank(message: 'Choisissez une personne source.')],
            ])
            ->add('target', EntityType::class, [
                'class' => Participant::class,
                'choice_label' => 'name',
                'label' => 'Ne doit pas offrir à',
                'placeholder' => 'Choisir…',
                'help' => 'La personne exclue comme destinataire.',
                'constraints' => [new Assert\NotBlank(message: 'Choisissez une personne cible.')],
            ])
            ->add('mutual', CheckboxType::class, [
                'label' => 'Exclusion mutuelle (couple, binôme…)',
                'mapped' => false,
                'required' => false,
                'help' => 'Crée automatiquement l’exclusion dans les deux sens.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exclusion::class,
        ]);
    }
}
