<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a goal, a day, a curve, writing days or a count that does not hold together, or words
 * logged on a day that has not come.
 */
final class InvalidGoal extends InvalidArgumentException implements WritingGoalsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
