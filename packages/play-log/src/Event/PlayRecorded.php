<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * A play was recorded.
 */
final readonly class PlayRecorded implements PlayEvent
{
    public function __construct(public string $play, public string $game, public string $recordedBy)
    {
    }
}
