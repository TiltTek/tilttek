<?php

declare(strict_types=1);

namespace Tilttek\Sets\Exception;

use LogicException;
use Tilttek\Sets\Contract\PendingDeliveries;

/**
 * A completion store that cannot do what was asked of it: a pending delivery needs a store that also implements
 * {@see PendingDeliveries}.
 */
final class UnsupportedStore extends LogicException implements SetsException
{
    public static function withoutPendingDeliveries(string $store): self
    {
        return new self(\sprintf('The completion store %s does not implement %s: it cannot list the holders with undelivered completions.', $store, PendingDeliveries::class));
    }
}
