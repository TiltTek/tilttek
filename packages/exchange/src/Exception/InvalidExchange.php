<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a key, a reward, a quantity, an allowance, an offer or a configuration that does not
 * hold together.
 */
final class InvalidExchange extends InvalidArgumentException implements ExchangeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
