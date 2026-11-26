<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Event;

/**
 * A condition was applied to a character.
 */
final readonly class ConditionApplied implements ConditionEvent
{
    public function __construct(public string $character, public string $instance, public string $condition)
    {
    }
}
