<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * The game master who runs a session said they are absent: the session waits for a substitute, or to be cancelled.
 */
final readonly class GameMasterAbsent implements SessionEvent
{
    public function __construct(public string $series, public string $session, public string $gameMaster, public bool $late)
    {
    }
}
