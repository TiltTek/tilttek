<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value the library refuses: a season, a schedule, an event, a content or a multiplier that does not hold together,
 * or a definition it cannot read.
 */
final class InvalidSeason extends InvalidArgumentException implements SeasonalEventsException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
