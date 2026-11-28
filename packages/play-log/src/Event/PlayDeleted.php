<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * The member who recorded a play deleted it.
 */
final readonly class PlayDeleted implements PlayEvent
{
    public function __construct(public string $play, public string $game, public string $recordedBy)
    {
    }
}
