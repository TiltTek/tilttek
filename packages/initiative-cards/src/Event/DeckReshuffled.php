<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * The discard pile was shuffled back into the draw pile, after a card that reshuffles or for want of cards.
 */
final readonly class DeckReshuffled implements InitiativeEvent
{
    public function __construct(public string $encounter)
    {
    }
}
