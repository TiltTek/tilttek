<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application\Contract;

use Phil\Resource\ResourceReference;

/**
 * Whether the current viewer may watch a table's tower: the application answers, from its own members and tables.
 * Only an allowed viewer is given the subscription to the table's private topic and the entries of its log.
 */
interface TableAccess
{
    public function canWatch(ResourceReference $table): bool;
}
