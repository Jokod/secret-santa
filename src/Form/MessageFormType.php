<?php

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class MessageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('body', TextareaType::class, [
            'label' => 'Votre message',
            'mapped' => false,
            'help' => sprintf('Maximum %d caractères. L’identité du Santa reste secrète.', Message::MAX_LENGTH),
            'constraints' => [
                new Assert\NotBlank(message: 'Écrivez un message avant d’envoyer.'),
                new Assert\Length(
                    max: Message::MAX_LENGTH,
                    maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.'
                ),
            ],
            'attr' => [
                'rows' => 4,
                'maxlength' => Message::MAX_LENGTH,
                'placeholder' => 'Une question sur la taille, une disponibilité…',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
