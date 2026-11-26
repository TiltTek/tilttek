<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Exception;

use InvalidArgumentException;

/**
 * An attendance criterion the library refuses: a count out of bounds, or an event or a place that does not fit in the
 * context of an activity.
 */
final class InvalidCriterion extends InvalidArgumentException implements CheckInRewardsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
