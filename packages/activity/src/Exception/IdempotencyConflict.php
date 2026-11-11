<?php

declare(strict_types=1);

namespace Tilttek\Activity\Exception;

use RuntimeException;
use Tilttek\Activity\IdempotencyKey;

/**
 * An idempotency key already recorded for another gesture: another member, verb, object, quantity or context. The
 * same key for the same gesture is a duplicate, not a conflict; the same key for a different gesture is a bug in the
 * application that builds its keys, and recording it would lose one of the two gestures.
 */
final class IdempotencyConflict extends RuntimeException implements ActivityException
{
    public static function on(IdempotencyKey $key): self
    {
        return new self(\sprintf('The idempotency key "%s" is already recorded for another gesture.', $key->value));
    }
}
