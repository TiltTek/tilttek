<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Exception;

use InvalidArgumentException;

/**
 * A guess the library does not check: not UTF-8, empty once normalized, or longer than any answer can be. It is not counted as
 * an attempt.
 */
final class InvalidAnswer extends InvalidArgumentException implements PuzzleBuilderException
{
    public static function notText(): self
    {
        return new self('An answer is UTF-8 text.');
    }

    public static function empty(): self
    {
        return new self('The answer holds nothing once normalized.');
    }

    public static function tooLong(int $max): self
    {
        return new self(\sprintf('An answer holds %d characters at most.', $max));
    }
}
