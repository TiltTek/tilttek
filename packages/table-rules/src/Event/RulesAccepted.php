<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Event;

/**
 * A player accepted a version of the table's rules.
 */
final readonly class RulesAccepted implements RulesEvent
{
    public function __construct(public string $table, public string $player, public int $version)
    {
    }
}
