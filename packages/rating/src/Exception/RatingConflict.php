<?php

declare(strict_types=1);

namespace Tilttek\Rating\Exception;

use RuntimeException;

/**
 * The ratings changed between their reading and their writing: another result of the same players was recorded at the
 * same time. Nothing was written; the result is recorded again.
 */
final class RatingConflict extends RuntimeException implements RatingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
