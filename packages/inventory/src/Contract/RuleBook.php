<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Contract;

use Tilttek\Inventory\CarryRules;

/**
 * The carrying rules of the game systems, by their keys: the application builds them from the systems it knows and the
 * variants a table plays with. An implementation keeps no state between two requests beside what it reads.
 */
interface RuleBook
{
    /**
     * The carrying rules of the system, or null when it declares none: its inventories are then never weighed.
     */
    public function rules(string $system): ?CarryRules;
}
