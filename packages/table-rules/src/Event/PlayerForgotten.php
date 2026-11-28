<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Event;

/**
 * The decision of a player on the table's rules was forgotten: the player left the table, or asked to be forgotten.
 */
final readonly class PlayerForgotten implements RulesEvent
{
    public function __construct(public string $table, public string $player)
    {
    }
}
