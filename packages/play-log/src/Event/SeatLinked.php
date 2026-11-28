<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * A member's seat of a play was confirmed, by them or by their consent: the play counts for them from now on.
 */
final readonly class SeatLinked implements PlayEvent
{
    public function __construct(public string $play, public string $game, public string $member)
    {
    }
}
