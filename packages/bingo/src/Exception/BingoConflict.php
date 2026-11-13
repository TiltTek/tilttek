<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Exception;

use RuntimeException;

/**
 * A game or a card changed in the store since it was read: the change was made from a version that is no longer the
 * stored one. The service reads it again and retries a few times before letting this through.
 */
final class BingoConflict extends RuntimeException implements BingoException
{
    public static function at(string $what, string $id, int $expected, ?int $stored): self
    {
        return new self(null === $stored
            ? \sprintf('The %s %s is not stored.', $what, $id)
            : \sprintf('The %s %s is at version %d, not %d.', $what, $id, $stored, $expected));
    }
}
