<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Random\Randomizer;

/**
 * The Fisher-Yates shuffle, from the last place to the second: each place swaps with a place drawn among itself and
 * those before it, by `Randomizer::getInt()`. With the randomizer of a seed of `tilttek/dice`, the same seed, client
 * seed and number give the same order, in any language that implements the same engine and the same draw.
 */
final class Shuffler
{
    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public static function shuffle(array $items, Randomizer $randomizer): array
    {
        $last = \count($items) - 1;

        for ($place = $last; $place >= 1; --$place) {
            $other = $randomizer->getInt(0, $place);
            [$items[$place], $items[$other]] = [$items[$other], $items[$place]];
        }

        return array_values($items);
    }
}
