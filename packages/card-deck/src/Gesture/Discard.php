<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\CardId;

/**
 * Puts a card on top of the discard pile, face up, from wherever it lies.
 */
final readonly class Discard implements Gesture
{
    public function __construct(
        public CardId $card,
    ) {
    }

    public function toArray(): array
    {
        return ['gesture' => 'discard', 'card' => $this->card->value];
    }
}
