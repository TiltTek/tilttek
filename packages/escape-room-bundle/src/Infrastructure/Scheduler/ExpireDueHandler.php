<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Scheduler;

use Tilttek\EscapeRoomBundle\Application\GameDesk;

/**
 * Handles `ExpireDue`, which it is registered for: ends the games whose countdown reached zero, the earliest first, as
 * many as the batch of the configuration. A game ended already, or whose time is no longer up, is left as it is, so
 * a message handled twice ends each game once.
 */
final readonly class ExpireDueHandler
{
    public function __construct(
        private GameDesk $desk,
        private int $batch = 100,
    ) {
    }

    /**
     * @return list<string> the games ended
     */
    public function __invoke(): array
    {
        return $this->desk->expireDue($this->batch);
    }
}
