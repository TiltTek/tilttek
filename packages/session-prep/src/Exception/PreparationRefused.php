<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Exception;

use DomainException;

/**
 * The change is well formed but not allowed: by whom it is asked, or on a preparation in its status.
 */
final class PreparationRefused extends DomainException implements SessionPrepException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
