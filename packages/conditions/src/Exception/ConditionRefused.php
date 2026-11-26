<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Exception;

use DomainException;

/**
 * The change is well formed but not allowed on the character in its state: too many conditions, or too many instances of one.
 */
final class ConditionRefused extends DomainException implements ConditionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
