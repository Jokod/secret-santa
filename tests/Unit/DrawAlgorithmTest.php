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
        self::assertEqualsCanonicalizing([1, 2, 3, 4], array_keys($pairs));
        self::assertEqualsCanonicalizing([1, 2, 3, 4], array_values($pairs));
    }

    public function testProducesSingleCycleWithoutMutualPairs(): void
    {
        $algo = new DrawAlgorithm(seed: 42);
        $ids = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
        $pairs = $algo->draw($ids, []);

        self::assertSingleCycle($pairs, $ids);
        foreach ($pairs as $santa => $target) {
            self::assertNotSame($santa, $pairs[$target], sprintf('mutual pair %d ↔ %d', $santa, $target));
        }
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
        self::assertSingleCycle($pairs, [1, 2, 3, 4]);
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
        self::assertSingleCycle($pairs, [1, 2, 3]);
    }

    public function testIsDeterministicWithSameSeed(): void
    {
        $a = (new DrawAlgorithm(seed: 123))->draw([10, 20, 30, 40, 50], [10 => [20]]);
        $b = (new DrawAlgorithm(seed: 123))->draw([10, 20, 30, 40, 50], [10 => [20]]);

        self::assertSame($a, $b);
        self::assertSingleCycle($a, [10, 20, 30, 40, 50]);
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
        self::assertSingleCycle($pairs, [1, 2, 3, 4]);
    }

    public function testDeduplicatesParticipantIds(): void
    {
        $algo = new DrawAlgorithm(seed: 5);
        $pairs = $algo->draw([1, 2, 3, 1, 2], []);

        self::assertCount(3, $pairs);
        self::assertEqualsCanonicalizing([1, 2, 3], array_keys($pairs));
        self::assertSingleCycle($pairs, [1, 2, 3]);
    }

    public function testFindsUniqueNarrowCycleAmongDenseExclusions(): void
    {
        // Only one Hamiltonian cycle exists: 1→2→3→4→5→1
        $exclusions = [
            1 => [3, 4, 5],
            2 => [1, 4, 5],
            3 => [1, 2, 5],
            4 => [1, 2, 3],
            5 => [2, 3, 4],
        ];

        $algo = new DrawAlgorithm(seed: 1);
        $pairs = $algo->draw([1, 2, 3, 4, 5], $exclusions);

        self::assertSame(2, $pairs[1]);
        self::assertSame(3, $pairs[2]);
        self::assertSame(4, $pairs[3]);
        self::assertSame(5, $pairs[4]);
        self::assertSame(1, $pairs[5]);
    }

    public function testHandlesMultipleMutualCouples(): void
    {
        // Couples 1↔2, 3↔4, 5↔6 cannot gift each other; still one family circle.
        $exclusions = [
            1 => [2],
            2 => [1],
            3 => [4],
            4 => [3],
            5 => [6],
            6 => [5],
        ];

        $algo = new DrawAlgorithm(seed: 11);
        $pairs = $algo->draw([1, 2, 3, 4, 5, 6], $exclusions);

        self::assertSingleCycle($pairs, [1, 2, 3, 4, 5, 6]);
        self::assertNotSame(2, $pairs[1]);
        self::assertNotSame(1, $pairs[2]);
        self::assertNotSame(4, $pairs[3]);
        self::assertNotSame(3, $pairs[4]);
        self::assertNotSame(6, $pairs[5]);
        self::assertNotSame(5, $pairs[6]);
    }

    public function testImpossibleWhenNoHamiltonianCycleDespitePartialLinks(): void
    {
        // Two cliques of 3 with no edges between them → no single circle of 6.
        $exclusions = [
            1 => [4, 5, 6],
            2 => [4, 5, 6],
            3 => [4, 5, 6],
            4 => [1, 2, 3],
            5 => [1, 2, 3],
            6 => [1, 2, 3],
        ];

        $algo = new DrawAlgorithm();
        $this->expectException(DrawException::class);
        $this->expectExceptionMessage('impossible');
        $algo->draw([1, 2, 3, 4, 5, 6], $exclusions);
    }

    /**
     * @param array<int, int> $pairs
     * @param list<int> $ids
     */
    private static function assertSingleCycle(array $pairs, array $ids): void
    {
        self::assertCount(count($ids), $pairs);

        $start = $ids[0];
        $current = $start;
        $visited = [];
        for ($i = 0; $i < count($ids); ++$i) {
            self::assertArrayHasKey($current, $pairs);
            self::assertNotContains($current, $visited, 'cycle broken or duplicate visit');
            $visited[] = $current;
            $current = $pairs[$current];
        }

        self::assertSame($start, $current, 'must close the circle');
        self::assertEqualsCanonicalizing($ids, $visited);
    }
}
