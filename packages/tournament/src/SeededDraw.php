<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * The order a published seed gives participants, the same each time it is asked for: the last resort of a tie, and the
 * initial order of participants without a seeding. Anyone holding the seed computes it again: the SHA-256 of the seed
 * and the participant, smallest first.
 */
final class SeededDraw
{
    private function __construct()
    {
    }

    /**
     * The participants in the order of the seed.
     *
     * @param list<string> $participants
     *
     * @return list<string>
     */
    public static function order(string $seed, array $participants): array
    {
        usort($participants, static fn (string $a, string $b): int => strcmp(self::key($seed, $a), self::key($seed, $b)));

        return $participants;
    }

    /**
     * The key of a participant in the draw: compare two keys as strings, the smallest first.
     */
    public static function key(string $seed, string $participant): string
    {
        return hash('sha256', $seed."\0".$participant);
    }
}
