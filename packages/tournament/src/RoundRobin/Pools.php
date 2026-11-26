<?php

declare(strict_types=1);

namespace Tilttek\Tournament\RoundRobin;

use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * Participants spread over pools in a snake, by seed: the first seeds head the pools in order, the next ones fill them
 * back in the opposite order, and so on, so that the pools are as even in strength as the seeds say. The pools are
 * named by letters, A first.
 */
final class Pools
{
    /** The most pools of a stage. */
    public const int MAX_POOLS = 26;

    private function __construct()
    {
    }

    /**
     * @param list<string> $participants in seed order
     *
     * @return array<string, list<string>> by pool
     *
     * @throws InvalidTournament when the number of pools is out of range, or a pool would have fewer than 2 participants
     */
    public static function snake(array $participants, int $count): array
    {
        if ($count < 1 || $count > self::MAX_POOLS) {
            throw InvalidTournament::because(\sprintf('A stage has 1 to %d pools.', self::MAX_POOLS));
        }

        if (\count($participants) < 2 * $count) {
            throw InvalidTournament::because('Each pool has 2 participants at least.');
        }

        $pools = [];

        foreach ($participants as $index => $participant) {
            $line = intdiv($index, $count);
            $column = $index % $count;
            $pools[\chr(65 + (0 === $line % 2 ? $column : $count - 1 - $column))][] = $participant;
        }

        return $pools;
    }
}
