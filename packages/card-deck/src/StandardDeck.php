<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

/**
 * The 52 cards of the French-suited deck, and as many jokers as asked: faces `AS` to `KC` — rank `A`, `2` to `10`,
 * `J`, `Q`, `K`, then suit `S`, `H`, `D`, `C` — and `JK`; identifiers `card-01` onwards, in that order, which say
 * nothing of the face.
 */
final class StandardDeck
{
    private const array RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

    private const array SUITS = ['S', 'H', 'D', 'C'];

    public static function create(int $jokers = 0, string $back = 'back'): Deck
    {
        $faces = [];

        foreach (self::SUITS as $suit) {
            foreach (self::RANKS as $rank) {
                $faces[] = $rank.$suit;
            }
        }

        $faces = [...$faces, ...array_fill(0, max(0, $jokers), 'JK')];
        $cards = [];

        foreach ($faces as $index => $face) {
            $cards[] = Card::of(\sprintf('card-%02d', $index + 1), $face, $back);
        }

        return new Deck(...$cards);
    }
}
