<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Exception;

use LogicException;

/**
 * A lookup or a roll the table does not allow: a key asked of a table looked up by range, a value asked of a table
 * looked up by key, a roll on a table without a die, a cell rolled that holds no dice, or a die that gives no number.
 */
final class LookupRefused extends LogicException implements ReferenceTablesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
