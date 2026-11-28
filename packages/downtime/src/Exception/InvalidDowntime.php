<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Exception;

use InvalidArgumentException;

/**
 * A value of the rules, of an entry or of a call is out of its shape or its bounds, or the parts of an activity do not
 * agree: an outcome its resolution names is missing, or the bands of a roll overlap or leave a gap.
 */
final class InvalidDowntime extends InvalidArgumentException implements DowntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
