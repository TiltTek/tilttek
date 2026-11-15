<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a kind of token, the rules of a table, an amount, an operation identifier, a reason or
 * a ledger entry it cannot take or read.
 */
final class InvalidTokenRule extends InvalidArgumentException implements LuckTokensException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
