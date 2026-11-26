<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A player declared a vacation: their clock does not run while it lasts.
 */
final readonly class VacationDeclared implements PlayerEvent
{
    public function __construct(public string $clock, public string $player)
    {
    }
}
