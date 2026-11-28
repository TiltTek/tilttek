<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Exception;

use RuntimeException;

/**
 * An entry changed since it was read, or a new one has the identifier of a kept one: nothing is kept, and the change is
 * made again on what is read anew.
 */
final class DowntimeConflict extends RuntimeException implements DowntimeException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The entry %s changed since it was read.', $id));
    }
}
