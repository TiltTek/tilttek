<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Event;

use Tilttek\Conditions\EndReason;

/**
 * A condition ended on a character, and why.
 */
final readonly class ConditionEnded implements ConditionEvent
{
    public function __construct(public string $character, public string $instance, public string $condition, public EndReason $reason)
    {
    }
}
