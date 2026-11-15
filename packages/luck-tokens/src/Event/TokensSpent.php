<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Event;

use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * Tokens spent by their holder or a game master: the moment a roller offers its reroll, or a dice tower shows the
 * spending. Dispatched once the entry is in the ledger, never for an operation sent again.
 */
final readonly class TokensSpent
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
        return abs($this->entry->change);
    }
}
