<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: terms out of bounds, a code or an identifier that is not one, a first action whose
 * kind is not one, a read out of bounds.
 */
final class InvalidReferral extends InvalidArgumentException implements ReferralsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
