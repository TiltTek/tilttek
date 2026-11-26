<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\CardId;

/**
 * Turns a card over where it lies: face down becomes face up, and back. The draw pile refuses it, its cards always face
 * down.
 */
final readonly class Flip implements Gesture
{
    public function __construct(
        public CardId $card,
    ) {
    }

    public function toArray(): array
    {
        return ['gesture' => 'flip', 'card' => $this->card->value];
    }
}
