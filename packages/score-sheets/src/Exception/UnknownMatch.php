<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Exception;

use RuntimeException;

/**
 * A match the store does not hold.
 */
final class UnknownMatch extends RuntimeException implements ScoreSheetsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
