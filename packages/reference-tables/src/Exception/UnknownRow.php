<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Exception;

use OutOfBoundsException;

/**
 * A lookup that finds no row: a key the table does not hold, or a value no range of the table contains.
 */
final class UnknownRow extends OutOfBoundsException implements ReferenceTablesException
{
    public static function key(string $table, string $key): self
    {
        return new self(\sprintf('The table "%s" has no row "%s".', $table, $key));
    }

    public static function value(string $table, int $value): self
    {
        return new self(\sprintf('No row of the table "%s" covers %d.', $table, $value));
    }
}
