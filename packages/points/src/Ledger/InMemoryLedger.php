<?php

declare(strict_types=1);

namespace Tilttek\Points\Ledger;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Points\Account;
use Tilttek\Points\Erasure;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Recorded;

/**
 * The ledger in memory, for tests and for a process that keeps nothing.
 */
final class InMemoryLedger implements Ledger
{
    /**
     * @var array<int, LedgerEntry>
     */
    private array $entries = [];

    private int $last = 0;

    #[Override]
    public function append(Movement $movement, DateTimeImmutable $at): Recorded
    {
        $existing = $this->find($movement->key);

        if ($existing instanceof LedgerEntry) {
            return $this->duplicate($existing, $movement);
        }

        $entry = new LedgerEntry(++$this->last, $movement, $at);
        $this->entries[$entry->position] = $entry;

        return new Recorded($entry, true);
    }

    #[Override]
    public function appendCovered(Movement $movement, DateTimeImmutable $at): Recorded
    {
        $existing = $this->find($movement->key);

        if ($existing instanceof LedgerEntry) {
            return $this->duplicate($existing, $movement);
        }

        $balance = $this->balance($movement->debit, $movement->currency);

        if ($balance < $movement->amount) {
            throw InsufficientPoints::in($movement->debit, $movement->currency, $balance, $movement->amount);
        }

        return $this->append($movement, $at);
    }

    #[Override]
    public function find(MovementKey $key): ?LedgerEntry
    {
        return array_find($this->entries, static fn (LedgerEntry $entry): bool => $entry->movement->key->equals($key));
    }

    #[Override]
    public function causedBy(string $cause): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $entry): bool => $cause === $entry->movement->cause));
    }

    #[Override]
    public function balance(Account $account, string $currency): int
    {
        $balance = 0;

        foreach ($this->entries as $entry) {
            if ($currency === $entry->movement->currency) {
                $balance += $entry->movement->amountFor($account);
            }
        }

        return $balance;
    }

    #[Override]
    public function net(Account $to, Account $from, string $currency, ?DateTimeImmutable $since = null, ?DateTimeImmutable $until = null): int
    {
        $net = 0;

        foreach ($this->entries as $entry) {
            $movement = $entry->movement;

            if ($currency === $movement->currency
                && 0 !== $movement->amountFor($from)
                && (!$since instanceof DateTimeImmutable || $movement->occurredAt >= $since)
                && (!$until instanceof DateTimeImmutable || $movement->occurredAt < $until)) {
                $net += $movement->amountFor($to);
            }
        }

        return $net;
    }

    #[Override]
    public function statement(Account $account, ?string $currency, int $after, int $limit): array
    {
        Reading::check($after, $limit);
        $entries = [];

        foreach ($this->entries as $position => $entry) {
            if ($position > $after
                && 0 !== $entry->movement->amountFor($account)
                && (null === $currency || $currency === $entry->movement->currency)) {
                $entries[] = $entry;
            }
        }

        return \array_slice($entries, 0, $limit);
    }

    #[Override]
    public function membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): array
    {
        $accounts = [];

        foreach ($this->entries as $entry) {
            $movement = $entry->movement;

            if ($movement->credit->isMember() && $currency === $movement->currency && $movement->occurredAt <= $cutoff) {
                $accounts[$movement->credit->toString()] = $movement->credit;
            }
        }

        ksort($accounts, \SORT_STRING);

        return array_values($accounts);
    }

    #[Override]
    public function forgetDerived(?DateTimeImmutable $since): int
    {
        $before = \count($this->entries);
        $this->entries = array_filter(
            $this->entries,
            static fn (LedgerEntry $entry): bool => !$entry->movement->kind->isDerived()
                || ($since instanceof DateTimeImmutable && $entry->movement->occurredAt < $since),
        );

        return $before - \count($this->entries);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        $account = Account::member($member);
        $deleted = 0;
        $detached = 0;

        foreach ($this->entries as $position => $entry) {
            $movement = $entry->movement;
            $debited = $movement->debit->equals($account);

            if (!$debited && !$movement->credit->equals($account)) {
                continue;
            }

            $other = $debited ? $movement->credit : $movement->debit;

            if (!$other->isMember()) {
                unset($this->entries[$position]);
                ++$deleted;

                continue;
            }

            $this->entries[$position] = new LedgerEntry($position, new Movement(
                $movement->key,
                $movement->kind,
                $movement->currency,
                $debited ? Account::erased() : $movement->debit,
                $debited ? $movement->credit : Account::erased(),
                $movement->amount,
                $movement->occurredAt,
                $movement->cause,
                $movement->reverses,
            ), $entry->at);
            ++$detached;
        }

        return new Erasure($deleted, $detached);
    }

    /**
     * Empties the ledger: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->entries = [];
        $this->last = 0;
    }

    private function duplicate(LedgerEntry $existing, Movement $movement): Recorded
    {
        if (!$existing->movement->sameAs($movement)) {
            throw MovementConflict::on($movement->key);
        }

        return new Recorded($existing, false);
    }
}
