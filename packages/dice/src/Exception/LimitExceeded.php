<?php

declare(strict_types=1);

namespace Tilttek\Dice\Exception;

use RuntimeException;

/**
 * An expression, or a roll, that goes past one of the limits: refused, never truncated.
 */
final class LimitExceeded extends RuntimeException implements DiceException
{
    public static function of(string $limit, int $maximum, int $actual): self
    {
        return new self(\sprintf('The %s are limited to %d; this asks for %d.', $limit, $maximum, $actual));
    }
}
