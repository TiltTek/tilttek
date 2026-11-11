<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use RuntimeException;

/**
 * An operation on a reservation or an offer that is no longer open: confirming a cancelled reservation, accepting an
 * expired offer.
 */
final class ExchangeClosed extends RuntimeException implements ExchangeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
