<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\View;

use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\ZoneId;

/**
 * What one player — or a spectator, with no player — may see of a game, and nothing more: their own hand, the cards
 * face up, the number of cards of every zone, the commitment to the seed and the version to play against. Never the
 * order of the draw pile, the seed or the journal of gestures. It is the only thing to send to a browser.
 */
final readonly class PlayerView
{
    /**
     * @param list<ZoneView> $zones in the order the zones appeared in the game
     */
    public function __construct(
        public ?PlayerId $viewer,
        public string $commitment,
        public int $version,
        public bool $finished,
        public array $zones,
    ) {
    }

    public function zone(ZoneId $zone): ?ZoneView
    {
        foreach ($this->zones as $view) {
            if ($view->zone->equals($zone)) {
                return $view;
            }
        }

        return null;
    }

    /**
     * @return array{viewer: ?string, commitment: string, version: int, finished: bool, zones: list<array{zone: string, count: int, cards: list<array{card: ?string, face: ?string, back: string, face_up: bool}>}>}
     */
    public function toArray(): array
    {
        return [
            'viewer' => $this->viewer?->value,
            'commitment' => $this->commitment,
            'version' => $this->version,
            'finished' => $this->finished,
            'zones' => array_map(static fn (ZoneView $zone): array => $zone->toArray(), $this->zones),
        ];
    }
}
