<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Exception;

use InvalidArgumentException;

/**
 * A score the sheet refuses: a participant or a category it does not know, a computed category, a value out of its
 * bounds, a result too large to compute, participants or teams that do not hold together.
 */
final class InvalidEntry extends InvalidArgumentException implements ScoreSheetsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
