<?php

namespace App\Service;

use App\Exception\DrawException;

/**
 * Pure draw algorithm: one Hamiltonian cycle with exclusion constraints.
 *
 * Everyone is placed on a single circle (A→B→C→…→A). That forbids mutual
 * pairs (A↔B) and disjoint subgroups (e.g. a 3-cycle + an 8-cycle).
 *
 * Search is exhaustive backtracking on the directed graph of allowed gifts
 * (randomized neighbor order for variety). If a valid circle exists, it is found.
 *
 * @phpstan-type ExclusionMap array<int, list<int>>
 */
final class DrawAlgorithm
{
    public function __construct(
        private readonly ?int $seed = null,
        /** @deprecated Kept for BC with callers/tests; search is exhaustive. */
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
        $n = count($participantIds);
        if ($n < 3) {
            throw new DrawException('Il faut au moins 3 participants pour lancer le tirage.');
        }

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        $adjacency = $this->buildAdjacency($participantIds, $exclusions);
        foreach ($participantIds as $id) {
            if ($adjacency[$id] === []) {
                throw new DrawException(
                    'Tirage impossible avec ces exclusions. Assouplissez les contraintes (couples, etc.) puis réessayez.'
                );
            }
        }

        $order = $participantIds;
        $this->shuffle($order);

        $adj = [];
        foreach ($order as $id) {
            $neighbors = $adjacency[$id];
            $this->shuffle($neighbors);
            $adj[$id] = $neighbors;
        }

        $start = $order[0];
        $path = [$start];
        $visited = [$start => true];
        $cycle = $this->searchCycle($adj, $path, $visited, $n, $start);
        if ($cycle === null) {
            throw new DrawException(
                'Tirage impossible avec ces exclusions. Assouplissez les contraintes (couples, etc.) puis réessayez.'
            );
        }

        return $this->pathToPairs($cycle);
    }

    /**
     * @param list<int> $participantIds
     * @param ExclusionMap $exclusions
     *
     * @return array<int, list<int>>
     */
    private function buildAdjacency(array $participantIds, array $exclusions): array
    {
        $adjacency = [];
        foreach ($participantIds as $santaId) {
            $forbidden = array_flip($exclusions[$santaId] ?? []);
            $targets = [];
            foreach ($participantIds as $targetId) {
                if ($targetId === $santaId || isset($forbidden[$targetId])) {
                    continue;
                }
                $targets[] = $targetId;
            }
            $adjacency[$santaId] = $targets;
        }

        return $adjacency;
    }

    /**
     * @param array<int, list<int>> $adjacency
     * @param list<int> $path
     * @param array<int, true> $visited
     *
     * @return list<int>|null
     */
    private function searchCycle(array $adjacency, array $path, array $visited, int $n, int $start): ?array
    {
        $current = $path[array_key_last($path)];

        if (count($path) === $n) {
            return in_array($start, $adjacency[$current], true) ? $path : null;
        }

        foreach ($adjacency[$current] as $next) {
            if (isset($visited[$next])) {
                continue;
            }
            $visited[$next] = true;
            $path[] = $next;
            $found = $this->searchCycle($adjacency, $path, $visited, $n, $start);
            if ($found !== null) {
                return $found;
            }
            array_pop($path);
            unset($visited[$next]);
        }

        return null;
    }

    /**
     * @param list<int> $path
     *
     * @return array<int, int>
     */
    private function pathToPairs(array $path): array
    {
        $n = count($path);
        $pairs = [];
        for ($i = 0; $i < $n; ++$i) {
            $pairs[$path[$i]] = $path[($i + 1) % $n];
        }

        return $pairs;
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
