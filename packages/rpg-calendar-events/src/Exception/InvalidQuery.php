<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Exception;

use InvalidArgumentException;

/**
 * A question the catalog cannot answer: a year out of its range, an entry it does not hold, a filter that cannot be.
 */
final class InvalidQuery extends InvalidArgumentException implements RpgCalendarEventsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
