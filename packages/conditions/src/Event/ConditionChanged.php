<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Event;

/**
 * A condition in place changed: its value, or a new application refreshed it.
 */
final readonly class ConditionChanged implements ConditionEvent
{
    public function __construct(public string $character, public string $instance, public string $condition)
    {
    }
}
