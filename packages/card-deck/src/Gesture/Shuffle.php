<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\ZoneId;

/**
 * Shuffles the cards of one zone in place, face down, from the next number of the game's seed. It is not a new game:
 * the cards of the other zones stay where they are.
 */
final readonly class Shuffle implements Gesture
{
    public ZoneId $zone;

    public function __construct(?ZoneId $zone = null)
    {
        $this->zone = $zone ?? ZoneId::drawPile();
    }

    public function toArray(): array
    {
        return ['gesture' => 'shuffle', 'zone' => $this->zone->toString()];
    }
}
