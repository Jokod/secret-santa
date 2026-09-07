<?php

namespace App\Service;

use App\Exception\DrawException;

/**
 * Pure draw algorithm: derangement with exclusion constraints.
 *
 * @phpstan-type ExclusionMap array<int, list<int>>
 */
final class DrawAlgorithm
{
    public function __construct(
        private readonly ?int $seed = null,
        private readonly int $maxAttempts = 500,
    ) {
    }

    /**
     * @param list<int> $participantIds
     * @param ExclusionMap $exclusions sourceId => forbidden target ids
     *
     * @return array<int, int> santaId => targetId
     */
    public function draw(array $participantIds, array $exclusions = []): array
    {
        $participantIds = array_values(array_unique($participantIds));
        if (count($participantIds) < 3) {
            throw new DrawException('Il faut au moins 3 participants pour lancer le tirage.');
        }

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        for ($attempt = 0; $attempt < $this->maxAttempts; ++$attempt) {
            $targets = $participantIds;
            $this->shuffle($targets);

            $pairs = [];
            $ok = true;

            foreach ($participantIds as $index => $santaId) {
                $targetId = $targets[$index];
                if ($targetId === $santaId) {
                    $ok = false;
                    break;
                }
                $forbidden = $exclusions[$santaId] ?? [];
                if (in_array($targetId, $forbidden, true)) {
                    $ok = false;
                    break;
                }
                $pairs[$santaId] = $targetId;
            }

            if ($ok && count($pairs) === count($participantIds)) {
                return $pairs;
            }
        }

        throw new DrawException(
            'Tirage impossible avec ces exclusions. Assouplissez les contraintes (couples, etc.) puis réessayez.'
        );
    }

    /**
     * @param list<int> $items
     */
    private function shuffle(array &$items): void
    {
        $count = count($items);
        for ($i = $count - 1; $i > 0; --$i) {
            $j = $this->seed !== null ? mt_rand(0, $i) : random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
}
