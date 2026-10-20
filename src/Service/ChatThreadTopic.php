<?php

namespace App\Service;

use App\Entity\Participant;

/**
 * Opaque Mercure topic for a Santa ↔ target thread (no names / raw ids).
 */
final class ChatThreadTopic
{
    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function forPair(Participant $santa, Participant $target): string
    {
        $santaId = $santa->getId();
        $targetId = $target->getId();
        if ($santaId === null || $targetId === null) {
            throw new \InvalidArgumentException('Santa and target must be persisted before deriving a chat topic.');
        }

        $digest = hash_hmac('sha256', $santaId.':'.$targetId, $this->appSecret);

        return 'urn:santa:thread:'.$digest;
    }
}
