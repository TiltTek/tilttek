<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Exception;

use InvalidArgumentException;

/**
 * A sheet declaration the library refuses: a key out of its pattern, a category that refers to one declared after it,
 * an operation or a bound that does not hold together, data or JSON that does not decode.
 */
final class InvalidSheet extends InvalidArgumentException implements ScoreSheetsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
