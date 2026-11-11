<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Exception;

use InvalidArgumentException;

/**
 * A definition the application gave that the library refuses: an entry, a table, a box, a catalogue, the faces of a
 * result, a pending draw.
 */
final class InvalidLoot extends InvalidArgumentException implements LootException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
