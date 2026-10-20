<?php

namespace App\Service;

use App\Entity\Participant;

/**
 * Server-side anti-cheat for the easter-egg mini-game.
 *
 * A score is accepted only with a one-shot session started earlier,
 * after a plausible play duration, and under a hard score cap.
 */
final class ElfGameService
{
    public const DURATION_SECONDS = 60;
    public const MIN_ELAPSED_SECONDS = 55;
    public const MAX_ELAPSED_SECONDS = 90;
    /** Generous upper bound: ~2 catches/sec over the whole round. */
    public const MAX_SCORE_PER_SECOND = 2;

    public function maxScore(): int
    {
        return self::DURATION_SECONDS * self::MAX_SCORE_PER_SECOND;
    }

    public function startSession(Participant $participant, ?\DateTimeImmutable $now = null): string
    {
        $token = bin2hex(random_bytes(16));
        $participant
            ->setElfGameSessionToken($token)
            ->setElfGameStartedAt($now ?? new \DateTimeImmutable());

        return $token;
    }

    public function clearSession(Participant $participant): void
    {
        $participant
            ->setElfGameSessionToken(null)
            ->setElfGameStartedAt(null);
    }

    /**
     * @return array{ok: true, updated: bool, bestScore: int}|array{ok: false, error: string}
     */
    public function submitScore(
        Participant $participant,
        string $sessionToken,
        int $score,
        ?\DateTimeImmutable $now = null,
    ): array {
        $now ??= new \DateTimeImmutable();
        $storedToken = $participant->getElfGameSessionToken();
        $startedAt = $participant->getElfGameStartedAt();

        if ($storedToken === null || $startedAt === null) {
            return ['ok' => false, 'error' => 'no_session'];
        }

        if (!hash_equals($storedToken, $sessionToken)) {
            return ['ok' => false, 'error' => 'session'];
        }

        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        if ($elapsed < self::MIN_ELAPSED_SECONDS) {
            return ['ok' => false, 'error' => 'too_early'];
        }

        if ($elapsed > self::MAX_ELAPSED_SECONDS) {
            $this->clearSession($participant);

            return ['ok' => false, 'error' => 'expired'];
        }

        if ($score < 1 || $score > $this->maxScore()) {
            $this->clearSession($participant);

            return ['ok' => false, 'error' => 'invalid'];
        }

        $this->clearSession($participant);
        $updated = $participant->recordElfHighScore($score);

        return [
            'ok' => true,
            'updated' => $updated,
            'bestScore' => $participant->getElfHighScore(),
        ];
    }
}
