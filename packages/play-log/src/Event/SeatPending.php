<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * A member was given a seat of a play they have not confirmed: the moment to ask them.
 */
final readonly class SeatPending implements PlayEvent
{
    public function __construct(public string $play, public string $game, public string $member)
    {
    }
}
