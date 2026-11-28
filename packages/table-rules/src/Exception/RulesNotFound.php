<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Exception;

use RuntimeException;

/**
 * The table has no rulebook yet, or its rulebook has no version of the number.
 */
final class RulesNotFound extends RuntimeException implements TableRulesException
{
    public static function rulebook(string $table): self
    {
        return new self(\sprintf('The table %s has no rules published.', $table));
    }

    public static function version(string $table, int $number): self
    {
        return new self(\sprintf('The rules of the table %s have no version %d.', $table, $number));
    }
}
