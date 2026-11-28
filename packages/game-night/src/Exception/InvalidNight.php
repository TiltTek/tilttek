<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Exception;

use InvalidArgumentException;

/**
 * A value of a night, a table, a sign-up, a vote or a play is out of its shape or its bounds.
 */
final class InvalidNight extends InvalidArgumentException implements GameNightException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
