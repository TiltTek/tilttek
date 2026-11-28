<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Exception;

use RuntimeException;

/**
 * The rulebook changed since it was read: the change is not kept, and is made again on what is read anew.
 */
final class RulesConflict extends RuntimeException implements TableRulesException
{
    public static function changed(string $table): self
    {
        return new self(\sprintf('The rules of the table %s changed since they were read.', $table));
    }
}
