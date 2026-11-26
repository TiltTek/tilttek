<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\View;

use Tilttek\CardDeck\ZoneId;

/**
 * One zone as a viewer sees it: its number of cards and, except for the draw pile, its cards in order, each visible or
 * hidden. The draw pile shows its number of cards only.
 */
final readonly class ZoneView
{
    /**
     * @param list<SeenCard> $cards top first; empty for the draw pile
     */
    public function __construct(
        public ZoneId $zone,
        public int $count,
        public array $cards,
    ) {
    }

    /**
     * @return array{zone: string, count: int, cards: list<array{card: ?string, face: ?string, back: string, face_up: bool}>}
     */
    public function toArray(): array
    {
        return ['zone' => $this->zone->toString(), 'count' => $this->count, 'cards' => array_map(static fn (SeenCard $card): array => $card->toArray(), $this->cards)];
    }
}
