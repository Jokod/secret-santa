<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Exception\DrawException;
use Doctrine\ORM\EntityManagerInterface;

final class MessageService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppMailer $appMailer,
    ) {
    }

    public function sendFromSanta(Participant $santa, string $body): Message
    {
        $assignment = $santa->getAssignmentAsSanta();
        if ($assignment === null || $assignment->getTarget() === null) {
            throw new DrawException('Aucun tirage actif pour envoyer un message.');
        }

        $message = (new Message())
            ->setSanta($santa)
            ->setTarget($assignment->getTarget())
            ->setType(MessageType::ToTarget)
            ->setBody($body);

        $this->assertLength($message);
        $this->em->persist($message);
        $this->em->flush();
        $this->appMailer->sendMessageNotification($message, $assignment->getTarget());

        return $message;
    }

    public function sendFromTarget(Participant $target, string $body): Message
    {
        $assignment = $target->getAssignmentAsTarget();
        if ($assignment === null || $assignment->getSanta() === null) {
            throw new DrawException('Aucun tirage actif pour envoyer un message.');
        }

        $message = (new Message())
            ->setSanta($assignment->getSanta())
            ->setTarget($target)
            ->setType(MessageType::ToSanta)
            ->setBody($body);

        $this->assertLength($message);
        $this->em->persist($message);
        $this->em->flush();
        $this->appMailer->sendMessageNotification($message, $assignment->getSanta());

        return $message;
    }

    private function assertLength(Message $message): void
    {
        if (mb_strlen($message->getBody()) > Message::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Le message ne peut pas dépasser %d caractères.',
                Message::MAX_LENGTH
            ));
        }
        if ($message->getBody() === '') {
            throw new \InvalidArgumentException('Le message ne peut pas être vide.');
        }
    }
}
