<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Exception;

use OutOfBoundsException;

/**
 * A box or a table a catalogue does not hold.
 */
final class UnknownLoot extends OutOfBoundsException implements LootException
{
    public static function box(string $key): self
    {
        return new self(\sprintf('The catalogue holds no box "%s".', $key));
    }

    public static function table(string $key): self
    {
        return new self(\sprintf('The catalogue holds no table "%s".', $key));
    }
}
