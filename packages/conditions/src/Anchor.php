<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * Whose turns a duration in rounds counts: the affected character's, the source's — "until the end of your next turn" —
 * or the round itself, for a table that plays without turn order.
 */
enum Anchor: string
{
    case Target = 'target';
    case Source = 'source';
    case Round = 'round';
}
