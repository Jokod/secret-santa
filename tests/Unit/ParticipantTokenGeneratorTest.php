<?php

namespace App\Tests\Unit;

use App\Service\ParticipantTokenGenerator;
use PHPUnit\Framework\TestCase;

final class ParticipantTokenGeneratorTest extends TestCase
{
    public function testGenerates64HexCharacters(): void
    {
        $generator = new ParticipantTokenGenerator();
        $token = $generator->generate();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testGeneratesUniqueTokens(): void
    {
        $generator = new ParticipantTokenGenerator();
        $tokens = [];
        for ($i = 0; $i < 20; ++$i) {
            $tokens[] = $generator->generate();
        }

        self::assertCount(20, array_unique($tokens));
    }
}
