<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * What an operation wrote in the ledger, and whether it had been written already: an operation sent again under the
 * same identifier writes nothing more and is told nothing more.
 */
final readonly class Receipt
{
    /**
     * @param list<LedgerEntry> $entries
     */
    public function __construct(
        public string $operation,
        public array $entries,
        public bool $replayed = false,
    ) {
    }

    /**
     * The balance the operation left its first holder — the one who received, spent or gave —, or null for the start of
     * a session.
     */
    public function balance(): ?int
    {
        $first = $this->entries[0] ?? null;

        return null === $first?->kind ? null : $first->balance;
    }
}
