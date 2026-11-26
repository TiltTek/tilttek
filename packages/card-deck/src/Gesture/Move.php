<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\ZoneId;

/**
 * Moves a card from wherever it lies to the top or the bottom of a zone, face down or face up; a card moved to the draw
 * pile always lies face down.
 */
final readonly class Move implements Gesture
{
    public function __construct(
        public CardId $card,
        public ZoneId $to,
        public End $at = End::Top,
        public bool $faceUp = false,
    ) {
    }

    public function toArray(): array
    {
        return ['gesture' => 'move', 'card' => $this->card->value, 'to' => $this->to->toString(), 'at' => $this->at->value, 'face_up' => $this->faceUp];
    }
}
