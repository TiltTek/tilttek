<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Exception;

use OutOfBoundsException;

/**
 * A table, or a column of a table, asked for by a key the catalogue or the table does not hold.
 */
final class UnknownTable extends OutOfBoundsException implements ReferenceTablesException
{
    public static function named(string $key): self
    {
        return new self(\sprintf('The catalogue holds no table "%s".', $key));
    }

    public static function column(string $table, string $column): self
    {
        return new self(\sprintf('The table "%s" has no column "%s".', $table, $column));
    }
}
