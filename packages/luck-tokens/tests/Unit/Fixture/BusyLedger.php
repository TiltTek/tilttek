<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;

/**
 * A ledger another writer reaches first: before each of the first appends, it runs the other writer's operation, so
 * that the append comes after a position that is no longer the last.
 */
final class BusyLedger implements TokenLedger
{
    public int $appends = 0;

    /** The balances read so far. */
    public int $balances = 0;

    /**
     * @param list<Closure(): mixed> $others
     */
    public function __construct(
        public InMemoryTokenLedger $inner = new InMemoryTokenLedger(),
        public array $others = [],
    ) {
    }

    #[Override]
    public function entries(ResourceReference $table): array
    {
        return $this->inner->entries($table);
    }

    #[Override]
    public function last(ResourceReference $table): int
    {
        return $this->inner->last($table);
    }

    #[Override]
    public function balance(ResourceReference $table, string $kind, ResourceReference $holder): int
    {
        ++$this->balances;

        return $this->inner->balance($table, $kind, $holder);
    }

    #[Override]
    public function holders(ResourceReference $table): array
    {
        return $this->inner->holders($table);
    }

    #[Override]
    public function hasSession(ResourceReference $table, string $session): bool
    {
        return $this->inner->hasSession($table, $session);
    }

    #[Override]
    public function operation(ResourceReference $table, string $operation): array
    {
        return $this->inner->operation($table, $operation);
    }

    #[Override]
    public function eraseHolder(ResourceReference $member, ResourceReference $pseudonym): int
    {
        return $this->inner->eraseHolder($member, $pseudonym);
    }

    /**
     * @param list<LedgerEntry> $entries
     */
    #[Override]
    public function append(ResourceReference $table, int $after, array $entries): void
    {
        ++$this->appends;
        $other = array_shift($this->others);

        if (null !== $other) {
            $other();
        }

        $this->inner->append($table, $after, $entries);
    }
}
