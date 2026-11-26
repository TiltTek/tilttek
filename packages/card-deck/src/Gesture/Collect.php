<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\ZoneId;

/**
 * Moves every card of one zone, in their order, to the top or the bottom of another — the discard pile back under the
 * draw pile, say, before a `Shuffle` —, face down or face up; cards collected into the draw pile always lie face down.
 */
final readonly class Collect implements Gesture
{
    /**
     * @throws InvalidDefinition when both zones are the same
     */
    public function __construct(
        public ZoneId $from,
        public ZoneId $to,
        public End $at = End::Top,
        public bool $faceUp = false,
    ) {
        if ($from->equals($to)) {
            throw InvalidDefinition::because('A zone is collected into another one.');
        }
    }

    public function toArray(): array
    {
        return ['gesture' => 'collect', 'from' => $this->from->toString(), 'to' => $this->to->toString(), 'at' => $this->at->value, 'face_up' => $this->faceUp];
    }
}
