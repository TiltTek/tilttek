<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Event;

/**
 * A fact of a table's rules, by identifiers only: an application sends a message from it, and reads the rest from the
 * store.
 */
interface RulesEvent
{
    /** The identifier of the table. */
    public string $table { get; }
}
