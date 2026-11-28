<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Exception;

use InvalidArgumentException;

/**
 * A table, a column, a row, a cell or a range that breaks a rule of the library: the message says which and why.
 */
final class InvalidTable extends InvalidArgumentException implements ReferenceTablesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
