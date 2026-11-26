<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Exception;

use DomainException;

/**
 * The change is well formed but not allowed: by whom it is asked, on a session in its state, or at that moment.
 */
final class ScheduleRefused extends DomainException implements SchedulerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
