<?php

declare(strict_types=1);

namespace Tilttek\Rating\Exception;

use InvalidArgumentException;

/**
 * A value out of its range or shape: an identifier, a result, a setting of a rating system, or stored values that do
 * not read.
 */
final class InvalidRating extends InvalidArgumentException implements RatingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
