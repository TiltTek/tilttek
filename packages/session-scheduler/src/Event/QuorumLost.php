<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * An absence left fewer players not absent than the series' quorum: the game master decides.
 */
final readonly class QuorumLost implements SessionEvent
{
    public function __construct(public string $series, public string $session)
    {
    }
}
