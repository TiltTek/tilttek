<?php

declare(strict_types=1);

namespace Tilttek\Collections\Exception;

use OutOfBoundsException;

/**
 * A rarity, an item, a set, a pack or a copy is asked for by a code or an identifier none has.
 */
final class NotFound extends OutOfBoundsException implements CollectionsException
{
    public static function of(string $what, string $code): self
    {
        return new self(\sprintf('No %s is named "%s".', $what, $code));
    }
}
