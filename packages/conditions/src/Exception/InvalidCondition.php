<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Exception;

use InvalidArgumentException;

/**
 * A value of a definition, a catalogue, a duration, a modifier or a call is out of its shape or its bounds.
 */
final class InvalidCondition extends InvalidArgumentException implements ConditionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
