<?php

namespace App\Tests\Unit;

use App\Exception\DrawException;
use App\Service\DrawAlgorithm;
use PHPUnit\Framework\TestCase;

final class DrawAlgorithmTest extends TestCase
{
    public function testRequiresAtLeastThreeParticipants(): void
    {
        $algo = new DrawAlgorithm();

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('3 participants');

        $algo->draw([1, 2], []);
    }

    public function testNeverAssignsSelf(): void
    {
        $algo = new DrawAlgorithm(seed: 42);
        $pairs = $algo->draw([1, 2, 3, 4], []);

        foreach ($pairs as $santa => $target) {
            self::assertNotSame($santa, $target);
        }
        self::assertCount(4, $pairs);
        self::assertSame([1, 2, 3, 4], array_keys($pairs));
        self::assertEqualsCanonicalizing([1, 2, 3, 4], array_values($pairs));
    }

    public function testRespectsExclusions(): void
    {
        $algo = new DrawAlgorithm(seed: 7);
        // 1 cannot gift 2; 2 cannot gift 1
        $exclusions = [
            1 => [2],
            2 => [1],
        ];

        $pairs = $algo->draw([1, 2, 3, 4], $exclusions);

        self::assertNotSame(2, $pairs[1]);
        self::assertNotSame(1, $pairs[2]);
        foreach ($pairs as $santa => $target) {
            self::assertNotSame($santa, $target);
        }
    }

    public function testFailsWhenGraphIsImpossible(): void
    {
        $algo = new DrawAlgorithm(maxAttempts: 50);

        // Everyone excluded from everyone else -> impossible
        $ids = [1, 2, 3];
        $exclusions = [
            1 => [2, 3],
            2 => [1, 3],
            3 => [1, 2],
        ];

        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('impossible');

        $algo->draw($ids, $exclusions);
    }

    public function testDenseButSolvableExclusions(): void
    {
        $algo = new DrawAlgorithm(seed: 99, maxAttempts: 200);
        // Force a cycle: 1->2->3->1
        $exclusions = [
            1 => [3],
            2 => [1],
            3 => [2],
        ];

        $pairs = $algo->draw([1, 2, 3], $exclusions);

        self::assertSame(2, $pairs[1]);
        self::assertSame(3, $pairs[2]);
        self::assertSame(1, $pairs[3]);
    }

    public function testIsDeterministicWithSameSeed(): void
    {
        $a = (new DrawAlgorithm(seed: 123))->draw([10, 20, 30, 40, 50], [10 => [20]]);
        $b = (new DrawAlgorithm(seed: 123))->draw([10, 20, 30, 40, 50], [10 => [20]]);

        self::assertSame($a, $b);
    }

    public function testDrawWithoutSeedUsesRandomIntPath(): void
    {
        $algo = new DrawAlgorithm(seed: null, maxAttempts: 200);
        $pairs = $algo->draw([1, 2, 3, 4], []);

        foreach ($pairs as $santa => $target) {
            self::assertNotSame($santa, $target);
        }
        self::assertCount(4, $pairs);
        self::assertEqualsCanonicalizing([1, 2, 3, 4], array_values($pairs));
    }

    public function testDeduplicatesParticipantIds(): void
    {
        $algo = new DrawAlgorithm(seed: 5);
        $pairs = $algo->draw([1, 2, 3, 1, 2], []);

        self::assertCount(3, $pairs);
        self::assertEqualsCanonicalizing([1, 2, 3], array_keys($pairs));
    }
}
