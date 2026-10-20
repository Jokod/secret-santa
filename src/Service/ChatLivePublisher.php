<?php

namespace App\Service;

use App\Entity\Message;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Pushes a neutral JSON payload to the thread topic (no display labels / names).
 */
final class ChatLivePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly ChatThreadTopic $threadTopic,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Message $message): void
    {
        try {
            $payload = $this->payloadFor($message);
        } catch (\LogicException) {
            return;
        }

        $topic = $this->threadTopic->forPair($message->getSanta(), $message->getTarget());

        try {
            $this->hub->publish(new Update($topic, json_encode($payload, \JSON_THROW_ON_ERROR), true));
        } catch (\Throwable $e) {
            $this->logger->error('Mercure publish failed for chat message {id}.', [
                'id' => $payload['id'],
                'exception' => $e,
            ]);
        }
    }

    /**
     * Neutral JSON shape shared by AJAX responses and Mercure updates (no names / labels).
     *
     * @return array{id: int, type: string, body: string, createdAt: string}
     */
    public function payloadFor(Message $message): array
    {
        $id = $message->getId();
        if ($id === null) {
            throw new \LogicException('Message must be persisted before building a payload.');
        }

        return [
            'id' => $id,
            'type' => $message->getType()->value,
            'body' => $message->getBody(),
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
