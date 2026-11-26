<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A substitute game master runs a session; null when the series' game master runs it again.
 */
final readonly class SubstituteAssigned implements SessionEvent
{
    public function __construct(public string $series, public string $session, public ?string $gameMaster)
    {
    }
}
