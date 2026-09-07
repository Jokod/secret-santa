<?php

namespace App\Form;

use App\Entity\Wish;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WishType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. Pull en laine'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Taille, couleur, précisions utiles…',
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Lien boutique facultatif.',
                'attr' => ['placeholder' => 'https://…'],
            ])
            ->add('estimatedPrice', NumberType::class, [
                'label' => 'Prix estimé (€)',
                'scale' => 2,
                'help' => sprintf('Doit rester dans le budget (max %s €).', rtrim(rtrim(number_format((float) $options['budget_max'], 2, '.', ''), '0'), '.') ?: '50'),
                'attr' => ['min' => 0, 'step' => '0.01', 'inputmode' => 'decimal'],
            ])
            ->add('preferenceOrder', IntegerType::class, [
                'label' => 'Ordre de préférence',
                'help' => '1 = priorité haute.',
                'attr' => ['min' => 0],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Wish::class,
            'budget_max' => 50.0,
        ]);
        $resolver->setAllowedTypes('budget_max', ['int', 'float']);
    }
}
