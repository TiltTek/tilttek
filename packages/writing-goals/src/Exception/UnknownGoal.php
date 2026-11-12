<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Exception;

use RuntimeException;

/**
 * A goal asked for by an identifier the store does not know.
 */
final class UnknownGoal extends RuntimeException implements WritingGoalsException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No goal has the identifier "%s".', $id));
    }
}
