<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Event;

use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * Tokens granted to a holder, by a game master or, when the kind allows it, by anyone at the table. Dispatched once
 * the entry is in the ledger, never for an operation sent again.
 */
final readonly class TokensGranted
{
    public function __construct(
        public LedgerEntry $entry,
    ) {
    }

    /**
     * How many tokens: always positive.
     */
    public function amount(): int
    {
        return $this->entry->change;
    }
}
