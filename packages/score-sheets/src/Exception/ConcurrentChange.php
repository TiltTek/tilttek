<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Exception;

use RuntimeException;

/**
 * A change made on a version of the sheet that is no longer the stored one: another change was recorded first.
 */
final class ConcurrentChange extends RuntimeException implements ScoreSheetsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
