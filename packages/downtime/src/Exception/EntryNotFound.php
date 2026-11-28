<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Exception;

use RuntimeException;

/**
 * No entry has the identifier.
 */
final class EntryNotFound extends RuntimeException implements DowntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No entry has the identifier %s.', $id));
    }
}
