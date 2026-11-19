<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

/**
 * The entrants of a raffle as the draw and the proof list them: each once, in byte order, and their hash.
 *
 * @internal
 */
final class Entrants
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $entrants
     *
     * @return list<string>
     */
    public static function canonical(array $entrants): array
    {
        $entrants = array_unique($entrants, \SORT_STRING);
        sort($entrants, \SORT_STRING);

        return $entrants;
    }

    /**
     * The SHA-256 of the canonical JSON of `["tilttek-raffle/1", raffle, entrants]`, the entrants in byte order.
     *
     * @param list<string> $entrants
     */
    public static function hash(string $raffle, array $entrants): string
    {
        return Encoding::hash([Encoding::VERSION, $raffle, $entrants]);
    }

    /**
     * The seed of the draw: the SHA-256 of the canonical JSON of `["tilttek-raffle/1", raffle, entrants hash,
     * randomness]`, the randomness being the beacon round's or the seed the organiser revealed.
     */
    public static function seed(string $raffle, string $entrantsHash, string $randomness): string
    {
        return Encoding::hash([Encoding::VERSION, $raffle, $entrantsHash, $randomness]);
    }
}
