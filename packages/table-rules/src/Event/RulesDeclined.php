<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Event;

/**
 * A player declined a version of the table's rules.
 */
final readonly class RulesDeclined implements RulesEvent
{
    public function __construct(public string $table, public string $player, public int $version)
    {
    }
}
