<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Contract;

use Tilttek\ReferenceTables\Event\TableRolled;

/**
 * Told of each roll on a table, by identifiers and numbers only.
 */
interface TableObserver
{
    public function notify(TableRolled $event): void;
}
