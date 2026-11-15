<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Event;

use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * A session started: the entry that marks it, and the tokens it set by carry-over and refresh — none for a holder
 * whose tokens did not change. Dispatched once the entries are in the ledger, never for an operation sent again.
 */
final readonly class SessionStarted
{
    /**
     * @param list<LedgerEntry> $refreshes
     */
    public function __construct(
        public LedgerEntry $start,
        public array $refreshes,
    ) {
    }
}
