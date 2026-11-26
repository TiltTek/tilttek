<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * The places of the seeds in a bracket, so that the two first seeds meet in the final at the earliest, the four first
 * in the semi-finals, and each seed meets the weakest seed left in the first round: 1 against 8, 4 against 5, 2 against
 * 7, 3 against 6 in a bracket of eight.
 */
final class SeedOrder
{
    /** The largest bracket. */
    public const int MAX_SIZE = 256;

    private function __construct()
    {
    }

    /**
     * The seeds, from 1, in the order of the bracket: each two in a row meet in the first round.
     *
     * @param int $size a power of 2, 2 to 256
     *
     * @return list<int>
     *
     * @throws InvalidTournament when the size is not a power of 2 in range
     */
    public static function of(int $size): array
    {
        $below = $size - 1;

        if ($size < 2 || $size > self::MAX_SIZE || 0 !== ($size & $below)) {
            throw InvalidTournament::because(\sprintf('A bracket has a power of 2 places, 2 to %d.', self::MAX_SIZE));
        }

        $order = [1];

        for ($places = 2; $places <= $size; $places *= 2) {
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $places + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }

    /**
     * The size of the bracket of this many participants: the smallest power of 2 that holds them.
     */
    public static function sizeFor(int $participants): int
    {
        $size = 2;

        while ($size < $participants) {
            $size *= 2;
        }

        return $size;
    }
}
