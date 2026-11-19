<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Application\Contract;

use Phil\Resource\ResourceReference;

/**
 * Who the current viewer is at a table: the application answers, from its own members and tables. A viewer without a
 * participant reference may not open the table's screens; only its game master opens the game master's screen.
 */
interface TableAccess
{
    /**
     * The current viewer's reference at the table, or null when they do not take part in it.
     */
    public function participant(ResourceReference $table): ?ResourceReference;

    /**
     * Whether the current viewer runs the table.
     */
    public function isGameMaster(ResourceReference $table): bool;
}
