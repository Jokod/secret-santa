<?php

namespace App\MessageHandler;

use App\Entity\Message;
use App\Enum\MessageType;
use App\Message\NotifyUnreadChatMessage;
use App\Repository\MessageRepository;
use App\Service\AppMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyUnreadChatMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messageRepository,
        private readonly AppMailer $appMailer,
    ) {
    }

    public function __invoke(NotifyUnreadChatMessage $command): void
    {
        $message = $this->em->find(Message::class, $command->messageId);
        if (!$message instanceof Message || $message->isRead() || $message->getEmailNotifiedAt() !== null) {
            return;
        }

        $recipient = $message->getType() === MessageType::ToTarget
            ? $message->getTarget()
            : $message->getSanta();

        $batch = $this->messageRepository->findUnnotifiedUnreadForRecipientInThread(
            $message->getSanta(),
            $message->getTarget(),
            $recipient,
        );
        if ($batch === []) {
            return;
        }

        $this->appMailer->sendMessageNotification($recipient, \count($batch), $message->getType());

        $now = new \DateTimeImmutable();
        foreach ($batch as $item) {
            $item->setEmailNotifiedAt($now);
        }
        $this->em->flush();
    }
}
