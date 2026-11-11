<?php

declare(strict_types=1);

namespace Tilttek\Activity\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a verb, a verb pattern, an idempotency key, a context, a quantity, a position or a
 * batch size.
 */
final class InvalidActivity extends InvalidArgumentException implements ActivityException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
