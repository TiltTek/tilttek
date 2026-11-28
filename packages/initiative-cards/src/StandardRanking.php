<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards;

use Override;
use Tilttek\CardDeck\Face;
use Tilttek\InitiativeCards\Contract\CardRanking;
use Tilttek\InitiativeCards\Exception\InvalidEncounter;

/**
 * The ranking of the faces of the 52-card deck of `tilttek/card-deck` and its jokers: a joker acts first, then the ace,
 * the king, the queen, the jack, the ten down to the two; two cards of one rank follow the order of the suits, spades,
 * hearts, diamonds, then clubs by default. A joker shuffles the deck again once its round is over.
 */
final readonly class StandardRanking implements CardRanking
{
    private const string JOKER = 'JK';

    private const array RANKS = ['A', 'K', 'Q', 'J', '10', '9', '8', '7', '6', '5', '4', '3', '2'];

    private const array SUITS = ['S', 'H', 'D', 'C'];

    /** @var array<string, int> the place of each suit, the first acting first */
    private array $suits;

    /**
     * @param list<string> $suits        the four suits, `S`, `H`, `D` and `C`, the first acting first
     * @param bool         $jokerShuffle whether a joker shuffles the deck again once its round is over
     *
     * @throws InvalidEncounter when the suits are not the four suits, each once
     */
    public function __construct(array $suits = self::SUITS, private bool $jokerShuffle = true)
    {
        $sorted = $suits;
        sort($sorted);
        $expected = self::SUITS;
        sort($expected);
        if ($sorted !== $expected) {
            throw InvalidEncounter::because('The order of the suits holds S, H, D and C, each once.');
        }

        $this->suits = array_flip($suits);
    }

    #[Override]
    public function ranks(Face $face): bool
    {
        return self::JOKER === $face->key || null !== $this->split($face->key);
    }

    #[Override]
    public function compare(Face $one, Face $other): int
    {
        return $this->place($one) <=> $this->place($other);
    }

    #[Override]
    public function reshuffles(Face $face): bool
    {
        return $this->jokerShuffle && self::JOKER === $face->key;
    }

    /**
     * @return array{int, int} the place of the rank, then of the suit; a joker before every other card
     *
     * @throws InvalidEncounter when the face is not of the 52-card deck or a joker
     */
    private function place(Face $face): array
    {
        if (self::JOKER === $face->key) {
            return [-1, 0];
        }

        return $this->split($face->key) ?? throw InvalidEncounter::because(\sprintf('The face %s is not of the 52-card deck.', $face->key));
    }

    /**
     * @return ?array{int, int}
     */
    private function split(string $key): ?array
    {
        $suit = substr($key, -1);
        $rank = array_search(substr($key, 0, -1), self::RANKS, true);
        if (false === $rank || !isset($this->suits[$suit])) {
            return null;
        }

        return [$rank, $this->suits[$suit]];
    }
}
