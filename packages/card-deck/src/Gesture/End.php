<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

/**
 * Where a card lands in its zone.
 */
enum End: string
{
    case Top = 'top';
    case Bottom = 'bottom';
}
