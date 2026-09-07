<?php

namespace App\Form;

use App\Entity\Participant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ParticipantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'help' => 'Prénom ou surnom unique dans la famille.',
                'attr' => [
                    'placeholder' => 'Ex. Marie',
                    'autocomplete' => 'name',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'help' => 'Utilisé pour le lien magique et les notifications.',
                'attr' => [
                    'placeholder' => 'marie@exemple.fr',
                    'autocomplete' => 'email',
                ],
            ])
            ->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                /** @var Participant $participant */
                $participant = $event->getData();
                $participant->setName($participant->getName());
                $participant->setEmail($participant->getEmail());
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participant::class,
        ]);
    }
}
