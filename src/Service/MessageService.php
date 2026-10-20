<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Participant;
use App\Enum\MessageType;
use App\Exception\DrawException;
use App\Message\NotifyUnreadChatMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessageService
{
    /** Wait before emailing — only if still unread when the job runs. */
    public const UNREAD_NOTIFY_DELAY_MS = 600_000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly ChatLivePublisher $chatLivePublisher,
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
        $this->chatLivePublisher->publish($message);
        $this->scheduleUnreadNotification($message);

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
        $this->chatLivePublisher->publish($message);
        $this->scheduleUnreadNotification($message);

        return $message;
    }

    /**
     * Mark unread messages in a thread that were addressed to the viewer.
     *
     * @param list<Message> $thread
     */
    public function markThreadReadForViewer(array $thread, Participant $viewer): void
    {
        $changed = false;
        foreach ($thread as $message) {
            if ($this->markReadIfRecipient($message, $viewer)) {
                $changed = true;
            }
        }
        if ($changed) {
            $this->em->flush();
        }
    }

    /**
     * Mark a single message as read when the viewer is its recipient.
     *
     * @return bool true if the message was marked (or already read by this recipient)
     */
    public function markReadForRecipient(Message $message, Participant $viewer): bool
    {
        if (!$this->isRecipient($message, $viewer)) {
            return false;
        }

        if (!$message->isRead()) {
            $message->setIsRead(true);
            $this->em->flush();
        }

        return true;
    }

    private function markReadIfRecipient(Message $message, Participant $viewer): bool
    {
        if ($message->isRead() || !$this->isRecipient($message, $viewer)) {
            return false;
        }

        $message->setIsRead(true);

        return true;
    }

    private function isRecipient(Message $message, Participant $viewer): bool
    {
        $viewerId = $viewer->getId();

        return match ($message->getType()) {
            MessageType::ToTarget => $message->getTarget()->getId() === $viewerId,
            MessageType::ToSanta => $message->getSanta()->getId() === $viewerId,
        };
    }

    private function scheduleUnreadNotification(Message $message): void
    {
        $id = $message->getId();
        if ($id === null) {
            return;
        }

        $this->bus->dispatch(
            new NotifyUnreadChatMessage($id),
            [new DelayStamp(self::UNREAD_NOTIFY_DELAY_MS)],
        );
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
