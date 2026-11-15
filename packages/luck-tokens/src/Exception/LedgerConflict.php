<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Exception;

use Phil\Resource\ResourceReference;
use RuntimeException;

/**
 * Two writers appended to the ledger of a table at once: the entries do not come right after the last one. The library
 * reads the ledger again and retries, three times in all, before it lets this exception through.
 */
final class LedgerConflict extends RuntimeException implements LuckTokensException
{
    public static function after(ResourceReference $table, int $expected, int $last): self
    {
        return new self(\sprintf('The ledger of the table %s ends at position %d; entries came after position %d.', $table->toString(), $last, $expected));
    }
}
