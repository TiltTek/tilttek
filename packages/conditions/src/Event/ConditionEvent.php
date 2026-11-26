<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Event;

/**
 * A fact of a character's conditions, by identifiers only: an application sends a message from it, and reads the rest
 * from the store.
 */
interface ConditionEvent
{
    /** The identifier of the character. */
    public string $character { get; }

    /** The identifier of the condition instance. */
    public string $instance { get; }

    /** The identifier of the condition. */
    public string $condition { get; }
}
