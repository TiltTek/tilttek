<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Exception;

use RuntimeException;

/**
 * No series or no session has the identifier.
 */
final class ScheduleNotFound extends RuntimeException implements SchedulerException
{
    public static function series(string $id): self
    {
        return new self(\sprintf('No series has the identifier %s.', $id));
    }

    public static function session(string $id): self
    {
        return new self(\sprintf('No session has the identifier %s.', $id));
    }
}
