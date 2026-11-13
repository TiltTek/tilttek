<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Contract;

use Tilttek\GameEngine\TableUpdate;

/**
 * Tells the players of a table that it changed — a Mercure hub, a WebSocket, a notification —: the update holds
 * identifiers only, and each player reads the view of their seat afterwards.
 */
interface TablePublisher
{
    public function publish(TableUpdate $update): void;
}
