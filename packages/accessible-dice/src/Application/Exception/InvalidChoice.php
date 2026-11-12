<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application\Exception;

use InvalidArgumentException;

/**
 * What the interface sent is not a roll it offers: an unknown die, a count or a modifier out of range.
 */
final class InvalidChoice extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
