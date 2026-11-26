<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Exception;

use RuntimeException;

/**
 * The clock changed since it was read, or a new one has the identifier of a kept one: the change is not kept, and is made again on what is read anew.
 */
final class ClockConflict extends RuntimeException implements TurnClockException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The clock %s changed since it was read.', $id));
    }
}
