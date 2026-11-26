<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A player said they are absent from a session; `late` when later than the series' notice, for the game master to decide.
 */
final readonly class AbsenceAnnounced implements SessionEvent
{
    public function __construct(public string $series, public string $session, public string $member, public bool $late)
    {
    }
}
