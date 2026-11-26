<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Exception;

use InvalidArgumentException;

/**
 * A value of a series, a session, a proposal or a call is out of its shape or its bounds.
 */
final class InvalidSchedule extends InvalidArgumentException implements SchedulerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
