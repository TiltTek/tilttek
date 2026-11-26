<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * Shows another face of a card of several faces, numbered from 0 in the order the card declares them; the card stays
 * face up or face down as it was.
 */
final readonly class TurnFace implements Gesture
{
    /**
     * @throws InvalidDefinition when the face is out of the bounds of any card
     */
    public function __construct(
        public CardId $card,
        public int $face,
    ) {
        if ($face < 0 || $face >= Card::MAX_FACES) {
            throw InvalidDefinition::because(\sprintf('A face is numbered 0 to %d.', Card::MAX_FACES - 1));
        }
    }

    public function toArray(): array
    {
        return ['gesture' => 'turn_face', 'card' => $this->card->value, 'face' => $this->face];
    }
}
