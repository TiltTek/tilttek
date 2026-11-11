<?php

declare(strict_types=1);

namespace Tilttek\Badges\Exception;

use OutOfBoundsException;

/**
 * A badge code the catalog does not hold.
 */
final class UnknownBadge extends OutOfBoundsException implements BadgesException
{
    public static function named(string $code): self
    {
        return new self(\sprintf('No badge is named "%s".', $code));
    }
}
