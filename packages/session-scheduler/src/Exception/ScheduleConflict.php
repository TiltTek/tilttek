<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Exception;

use RuntimeException;

/**
 * The series or the session changed since it was read, or a new one has the identifier of a kept one: the change is
 * not kept, and is made again on what is read anew.
 */
final class ScheduleConflict extends RuntimeException implements SchedulerException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('%s changed since it was read.', $id));
    }
}
