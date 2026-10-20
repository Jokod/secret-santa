<?php

namespace App\Tests\Unit;

use App\Entity\Participant;
use App\Service\ElfGameService;
use PHPUnit\Framework\TestCase;

final class ElfGameServiceTest extends TestCase
{
    public function testStartSessionStoresTokenAndTimestamp(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $now = new \DateTimeImmutable('2026-12-24 10:00:00');

        $token = $service->startSession($participant, $now);

        self::assertSame(32, strlen($token));
        self::assertSame($token, $participant->getElfGameSessionToken());
        self::assertSame($now, $participant->getElfGameStartedAt());
        self::assertSame(120, $service->maxScore());
    }

    public function testSubmitRejectsWithoutSession(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();

        $result = $service->submitScore($participant, 'missing', 10);

        self::assertFalse($result['ok']);
        self::assertSame('no_session', $result['error']);
    }

    public function testSubmitRejectsWrongSessionToken(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $started = new \DateTimeImmutable('2026-12-24 10:00:00');
        $service->startSession($participant, $started);

        $result = $service->submitScore($participant, 'wrong-token', 10, $started->modify('+60 seconds'));

        self::assertFalse($result['ok']);
        self::assertSame('session', $result['error']);
        self::assertNotNull($participant->getElfGameSessionToken());
    }

    public function testSubmitRejectsTooEarly(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $started = new \DateTimeImmutable('2026-12-24 10:00:00');
        $token = $service->startSession($participant, $started);

        $result = $service->submitScore($participant, $token, 10, $started->modify('+30 seconds'));

        self::assertFalse($result['ok']);
        self::assertSame('too_early', $result['error']);
        self::assertSame($token, $participant->getElfGameSessionToken());
    }

    public function testSubmitRejectsExpiredAndClearsSession(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $started = new \DateTimeImmutable('2026-12-24 10:00:00');
        $token = $service->startSession($participant, $started);

        $result = $service->submitScore($participant, $token, 10, $started->modify('+120 seconds'));

        self::assertFalse($result['ok']);
        self::assertSame('expired', $result['error']);
        self::assertNull($participant->getElfGameSessionToken());
        self::assertNull($participant->getElfGameStartedAt());
    }

    public function testSubmitRejectsInvalidScoreAndClearsSession(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $started = new \DateTimeImmutable('2026-12-24 10:00:00');
        $token = $service->startSession($participant, $started);

        $result = $service->submitScore($participant, $token, 999, $started->modify('+60 seconds'));

        self::assertFalse($result['ok']);
        self::assertSame('invalid', $result['error']);
        self::assertNull($participant->getElfGameSessionToken());
    }

    public function testSubmitAcceptsValidScoreOnce(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $started = new \DateTimeImmutable('2026-12-24 10:00:00');
        $token = $service->startSession($participant, $started);
        $now = $started->modify('+60 seconds');

        $first = $service->submitScore($participant, $token, 42, $now);
        self::assertTrue($first['ok']);
        self::assertTrue($first['updated']);
        self::assertSame(42, $first['bestScore']);
        self::assertNull($participant->getElfGameSessionToken());

        $replay = $service->submitScore($participant, $token, 80, $now);
        self::assertFalse($replay['ok']);
        self::assertSame('no_session', $replay['error']);
        self::assertSame(42, $participant->getElfHighScore());
    }

    public function testSubmitKeepsBestWhenNotImproved(): void
    {
        $service = new ElfGameService();
        $participant = (new Participant())->setElfHighScore(50);
        $started = new \DateTimeImmutable('2026-12-24 10:00:00');
        $token = $service->startSession($participant, $started);

        $result = $service->submitScore($participant, $token, 20, $started->modify('+60 seconds'));

        self::assertTrue($result['ok']);
        self::assertFalse($result['updated']);
        self::assertSame(50, $result['bestScore']);
    }

    public function testClearSession(): void
    {
        $service = new ElfGameService();
        $participant = new Participant();
        $service->startSession($participant);
        $service->clearSession($participant);

        self::assertNull($participant->getElfGameSessionToken());
        self::assertNull($participant->getElfGameStartedAt());
    }
}
