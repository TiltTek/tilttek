<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a theme the table does not know, an answer a question does not accept, a template, a
 * setting or a stored value that does not hold together, or a list longer than its bound.
 */
final class InvalidAnswer extends InvalidArgumentException implements TableSafetyException
{
    private const int EXCERPT_LENGTH = 80;

    /** The first 80 characters of a refused value, for a message that must not carry the whole of it. */
    public static function excerpt(string $value): string
    {
        return mb_substr($value, 0, self::EXCERPT_LENGTH);
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
