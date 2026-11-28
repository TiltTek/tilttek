<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * The member who recorded a play corrected it.
 */
final readonly class PlayCorrected implements PlayEvent
{
    public function __construct(public string $play, public string $game, public string $recordedBy)
    {
    }
}
