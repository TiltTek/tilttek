<?php

declare(strict_types=1);

namespace Tilttek\Points\Exception;

use RuntimeException;
use Tilttek\Points\MovementKey;

/**
 * A movement key already written for another movement: another kind, currency, account or amount. The same key for
 * the same movement is a duplicate, returned as it is; the same key for a different movement is a bug in the code that
 * builds the keys, and writing it would lose one of the two movements.
 */
final class MovementConflict extends RuntimeException implements PointsException
{
    public static function on(MovementKey $key): self
    {
        return new self(\sprintf('The movement key "%s" is already written for another movement.', $key->value));
    }
}
