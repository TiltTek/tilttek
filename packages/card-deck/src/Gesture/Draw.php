<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\ZoneId;

/**
 * A player draws one card — from the top, the bottom or at random — from a zone, the draw pile unless another is
 * named, to the end of their hand: face down, seen by them alone, or face up, seen by all.
 */
final readonly class Draw implements Gesture
{
    public ZoneId $zone;

    public function __construct(
        public PlayerId $player,
        public Pick $from = Pick::Top,
        ?ZoneId $zone = null,
        public bool $faceUp = false,
    ) {
        $this->zone = $zone ?? ZoneId::drawPile();
    }

    public function toArray(): array
    {
        return ['gesture' => 'draw', 'player' => $this->player->value, 'from' => $this->from->value, 'zone' => $this->zone->toString(), 'face_up' => $this->faceUp];
    }
}
