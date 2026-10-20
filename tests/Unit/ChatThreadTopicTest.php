<?php

namespace App\Tests\Unit;

use App\Entity\Participant;
use App\Service\ChatThreadTopic;
use PHPUnit\Framework\TestCase;

final class ChatThreadTopicTest extends TestCase
{
    public function testTopicIsStableOpaqueAndDistinctPerPair(): void
    {
        $topic = new ChatThreadTopic('secret-for-tests');
        $santa = $this->participant(1, 'Alice');
        $target = $this->participant(2, 'Bob');
        $other = $this->participant(3, 'Cara');

        $ab = $topic->forPair($santa, $target);
        $abAgain = $topic->forPair($santa, $target);
        $ac = $topic->forPair($santa, $other);

        self::assertSame($ab, $abAgain);
        self::assertNotSame($ab, $ac);
        self::assertMatchesRegularExpression('#^urn:santa:thread:[a-f0-9]{64}$#', $ab);
        self::assertStringNotContainsString('Alice', $ab);
        self::assertStringNotContainsString('Bob', $ab);
    }

    public function testReversedPairProducesDifferentTopic(): void
    {
        $topic = new ChatThreadTopic('secret-for-tests');
        $santa = $this->participant(1, 'Alice');
        $target = $this->participant(2, 'Bob');

        self::assertNotSame(
            $topic->forPair($santa, $target),
            $topic->forPair($target, $santa),
        );
    }

    public function testRequiresPersistedParticipants(): void
    {
        $topic = new ChatThreadTopic('secret-for-tests');
        $santa = (new Participant())->setName('A')->setEmail('a@t.com')->setTokenSecret(str_repeat('a', 64));
        $target = $this->participant(2, 'Bob');

        $this->expectException(\InvalidArgumentException::class);
        $topic->forPair($santa, $target);
    }

    private function participant(int $id, string $name): Participant
    {
        $p = (new Participant())
            ->setName($name)
            ->setEmail(strtolower($name).'@t.com')
            ->setTokenSecret(str_repeat('t', 64));
        $ref = new \ReflectionProperty(Participant::class, 'id');
        $ref->setValue($p, $id);

        return $p;
    }
}
