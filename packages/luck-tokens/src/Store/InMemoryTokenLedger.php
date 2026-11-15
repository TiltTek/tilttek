<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * The ledgers in memory, with their indexes, for tests and for a table that lives in one process. An erasure rewrites
 * the entries that name the member and builds the indexes again.
 */
final class InMemoryTokenLedger implements TokenLedger
{
    /** @var array<string, list<LedgerEntry>> */
    private array $entries = [];

    /** @var array<string, array<string, list<LedgerEntry>>> */
    private array $operations = [];

    /** @var array<string, array<string, int>> by table, then kind and holder */
    private array $balances = [];

    /** @var array<string, array<string, ResourceReference>> */
    private array $holders = [];

    /** @var array<string, array<string, true>> */
    private array $sessions = [];

    #[Override]
    public function entries(ResourceReference $table): array
    {
        return $this->entries[$table->toString()] ?? [];
    }

    #[Override]
    public function last(ResourceReference $table): int
    {
        return \count($this->entries($table));
    }

    #[Override]
    public function balance(ResourceReference $table, string $kind, ResourceReference $holder): int
    {
        return $this->balances[$table->toString()][$kind."\n".$holder->toString()] ?? 0;
    }

    #[Override]
    public function holders(ResourceReference $table): array
    {
        return array_values($this->holders[$table->toString()] ?? []);
    }

    #[Override]
    public function operation(ResourceReference $table, string $operation): array
    {
        return $this->operations[$table->toString()][$operation] ?? [];
    }

    #[Override]
    public function hasSession(ResourceReference $table, string $session): bool
    {
        return isset($this->sessions[$table->toString()][$session]);
    }

    #[Override]
    public function append(ResourceReference $table, int $after, array $entries): void
    {
        $key = $table->toString();
        $last = $this->last($table);

        if ($after !== $last) {
            throw LedgerConflict::after($table, $after, $last);
        }

        foreach ($entries as $index => $entry) {
            if ($entry->position !== $after + $index + 1 || !$entry->table->equals($table)) {
                throw InvalidTokenRule::because('The entries of an operation belong to the table of the ledger and follow its last one, by position.');
            }
        }

        foreach ($entries as $entry) {
            $this->entries[$key][] = $entry;
            $this->index($key, $entry);
        }
    }

    #[Override]
    public function eraseHolder(ResourceReference $member, ResourceReference $pseudonym): int
    {
        if ($member->equals($pseudonym)) {
            throw InvalidTokenRule::because('A member is erased under a pseudonym of their own, not their reference.');
        }

        $erased = 0;

        foreach ($this->entries as $key => $entries) {
            $this->entries[$key] = [];
            unset($this->operations[$key], $this->balances[$key], $this->holders[$key], $this->sessions[$key]);

            foreach ($entries as $entry) {
                $rewritten = $entry->pseudonymized($member, $pseudonym);

                if ($rewritten !== $entry) {
                    ++$erased;
                }

                $this->entries[$key][] = $rewritten;
                $this->index($key, $rewritten);
            }
        }

        return $erased;
    }

    private function index(string $key, LedgerEntry $entry): void
    {
        $this->operations[$key][$entry->operation][] = $entry;

        if (null !== $entry->kind) {
            $this->balances[$key][$entry->kind."\n".$entry->holder->toString()] = $entry->balance;
            $this->holders[$key][$entry->holder->toString()] ??= $entry->holder;
        }

        if (EntryType::SessionStarted === $entry->type) {
            $this->sessions[$key][$entry->session ?? ''] = true;
        }
    }
}
