<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * Where the ledgers of the tables are kept. A ledger only grows: entries are appended after the last one, never changed
 * nor removed, so that the game master and the players audit every token handed out, spent and passed. The one
 * rewriting a store does is the erasure of a member, by `eraseHolder()`: their reference gives way to a pseudonym in
 * every entry, and the positions, the changes and the balances stay as they were.
 *
 * Each entry carries the balance it leaves, so that a holder's balance is that of their last entry of the kind: a
 * store answers it from an index rather than by reading the whole ledger.
 */
interface TokenLedger
{
    /**
     * The entries of the table's ledger, by position; none for a table that has none.
     *
     * @return list<LedgerEntry>
     */
    public function entries(ResourceReference $table): array;

    /**
     * The position of the table's last entry; 0 for a table that has none.
     */
    public function last(ResourceReference $table): int;

    /**
     * The balance left by the holder's last entry of the kind; 0 when there is none.
     */
    public function balance(ResourceReference $table, string $kind, ResourceReference $holder): int;

    /**
     * The holders the table's entries of a kind name, in the order they first appear.
     *
     * @return list<ResourceReference>
     */
    public function holders(ResourceReference $table): array;

    /**
     * The entries an operation wrote in the table's ledger; none for an operation not done.
     *
     * @return list<LedgerEntry>
     */
    public function operation(ResourceReference $table, string $operation): array;

    /**
     * Whether the table's ledger holds the start of this session.
     */
    public function hasSession(ResourceReference $table, string $session): bool;

    /**
     * Appends the entries of one operation, all or none, right after position `$after`, the last the caller read.
     *
     * @param list<LedgerEntry> $entries numbered from `$after + 1`
     *
     * @throws LedgerConflict   when the ledger no longer ends at `$after`
     * @throws InvalidTokenRule when an entry is of another table, or not numbered from `$after + 1`
     */
    public function append(ResourceReference $table, int $after, array $entries): void;

    /**
     * Writes the pseudonym in place of the member, as holder and as actor, in every entry of every table — and so in
     * the balances and the holders it indexes —, and clears the reason of the entries the member holds; the positions,
     * changes, balances and order left as they were.
     *
     * @return int the entries rewritten; 0 when no entry names the member, as once they are erased
     *
     * @throws InvalidTokenRule when the pseudonym is the member
     */
    public function eraseHolder(ResourceReference $member, ResourceReference $pseudonym): int;
}
