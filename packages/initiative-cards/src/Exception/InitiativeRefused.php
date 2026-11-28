<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Exception;

use DomainException;

/**
 * The change is well formed but not allowed: by whom it is asked, or on an encounter in its state.
 */
final class InitiativeRefused extends DomainException implements InitiativeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
