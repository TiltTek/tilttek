<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

/**
 * Where a card is drawn from in its zone.
 */
enum Pick: string
{
    case Top = 'top';
    case Bottom = 'bottom';

    /** A card at random, drawn from the seed of the game. */
    case Random = 'random';
}
