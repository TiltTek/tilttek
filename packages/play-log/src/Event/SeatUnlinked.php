<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * A member's confirmed seat no longer counts for them: they declined it, it was taken off, or the play was deleted.
 */
final readonly class SeatUnlinked implements PlayEvent
{
    public function __construct(public string $play, public string $game, public string $member)
    {
    }
}
