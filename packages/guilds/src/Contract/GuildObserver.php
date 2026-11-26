<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Contract;

use Tilttek\Guilds\GoalReached;
use Tilttek\Guilds\GuildChanged;

/**
 * Told once of each change of a guild and of each goal reached, after the store wrote it: the application relays the
 * event — a notification, a message on its bus — if it wants to. An implementation keeps no state between two calls.
 */
interface GuildObserver
{
    public function changed(GuildChanged $event): void;

    public function goalReached(GoalReached $event): void;
}
