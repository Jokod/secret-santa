<?php

namespace App\Service;

final class ParticipantTokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
