<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use OutOfBoundsException;

/**
 * A reservation or an offer asked for by a key nothing is written under.
 */
final class UnknownExchange extends OutOfBoundsException implements ExchangeException
{
    public static function reservation(string $key): self
    {
        return new self(\sprintf('No reservation is written under the key "%s".', $key));
    }

    public static function offer(string $key): self
    {
        return new self(\sprintf('No offer is written under the key "%s".', $key));
    }
}
