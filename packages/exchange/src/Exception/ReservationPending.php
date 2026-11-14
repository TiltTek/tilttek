<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use RuntimeException;

/**
 * A reservation whose points are being paid, by another process or by an interrupted one: it cannot be confirmed nor
 * cancelled until the payment ends, or until its lifetime does. The operation may be asked again a moment later.
 */
final class ReservationPending extends RuntimeException implements ExchangeException
{
    public static function of(string $key): self
    {
        return new self(\sprintf('The points of the reservation "%s" are being paid; ask again in a moment.', $key));
    }
}
