<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Exception;

use InvalidArgumentException;

/**
 * An entry that cannot be read, or that a catalog cannot take.
 */
final class InvalidEntry extends InvalidArgumentException implements RpgCalendarEventsException
{
    public static function because(string $id, string $reason): self
    {
        return new self(\sprintf('The entry "%s" %s', mb_substr($id, 0, 100), $reason));
    }
}
