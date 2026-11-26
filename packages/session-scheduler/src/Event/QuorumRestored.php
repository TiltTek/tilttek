<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A player coming brought a session back to the series' quorum.
 */
final readonly class QuorumRestored implements SessionEvent
{
    public function __construct(public string $series, public string $session)
    {
    }
}
