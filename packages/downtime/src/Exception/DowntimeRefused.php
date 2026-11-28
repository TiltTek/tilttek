<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Exception;

use DomainException;

/**
 * The service does not allow the change: the member does not play the character nor run the campaign, the entry is not
 * at the stage the change starts from, the queue of the character is full, or an entry has the identifier.
 */
final class DowntimeRefused extends DomainException implements DowntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
