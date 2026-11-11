<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use OutOfBoundsException;

/**
 * A reward asked for by a code the catalogue does not hold.
 */
final class UnknownReward extends OutOfBoundsException implements ExchangeException
{
    public static function named(string $code): self
    {
        return new self(\sprintf('The catalogue holds no reward "%s".', $code));
    }
}
