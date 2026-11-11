<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use RuntimeException;

/**
 * A key already written for one reservation, transfer or offer, given again for another one.
 */
final class ExchangeConflict extends RuntimeException implements ExchangeException
{
    public static function on(string $what, string $key): self
    {
        return new self(\sprintf('The key "%s" is already written for another %s.', $key, $what));
    }
}
