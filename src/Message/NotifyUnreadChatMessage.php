<?php

namespace App\Message;

/**
 * Dispatched with a DelayStamp: send an email only if the chat message is still unread.
 */
final class NotifyUnreadChatMessage
{
    public function __construct(
        public readonly int $messageId,
    ) {
    }
}
