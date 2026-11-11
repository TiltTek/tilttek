<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Exception;

use InvalidArgumentException;

/**
 * A shop code or a draw text that does not read as the dice of its box: typed at a register, or read back from an
 * order.
 */
final class InvalidCode extends InvalidArgumentException implements LootException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
