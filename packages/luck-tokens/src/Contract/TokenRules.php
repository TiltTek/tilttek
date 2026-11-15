<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\TableRules;

/**
 * The rules of the tables: the kinds of tokens each hands out and its game masters.
 */
interface TokenRules
{
    /**
     * The table's rules, or null for a table that hands out no tokens.
     */
    public function rulesFor(ResourceReference $table): ?TableRules;
}
