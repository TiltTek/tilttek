<?php

declare(strict_types=1);

namespace Tilttek\Badges\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a badge, a criterion, a tier, a catalog, a progress, an award, a profile or a client that
 * does not hold together.
 */
final class InvalidBadge extends InvalidArgumentException implements BadgesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
