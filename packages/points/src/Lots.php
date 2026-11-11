<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateTimeImmutable;

/**
 * What is left of each credit to a member's account in one currency, the movements applied in the order the ledger
 * wrote them.
 *
 * Each credit opens a lot that expires its currency's lifetime after the credit's moment. A debit that names a lot —
 * the reversal of the points an activity earned, the expiry of a lot — takes from that lot first; every debit then
 * takes from the oldest lots still alive at its moment, then from the oldest of the others. What no lot covers is a
 * debt, which the next credits pay before opening a lot: a cancellation may leave a balance below zero, and the
 * points earned next fill it first.
 */
final class Lots
{
    /**
     * @var array<int, Lot>
     */
    private array $lots = [];

    private int $debt = 0;

    public function __construct(
        private readonly Account $account,
        private readonly Currency $currency,
    ) {
    }

    public function apply(LedgerEntry $entry): void
    {
        $movement = $entry->movement;

        if ($movement->currency !== $this->currency->code) {
            return;
        }

        if ($movement->credit->equals($this->account)) {
            $this->credit($movement);
        } elseif ($movement->debit->equals($this->account)) {
            $this->debit($movement->amount, $movement->reverses, $movement->occurredAt);
        }
    }

    /**
     * The lots with points left, oldest first.
     *
     * @return list<Lot>
     */
    public function open(): array
    {
        return array_values(array_filter($this->sorted(), static fn (Lot $lot): bool => $lot->remaining > 0));
    }

    /**
     * What no lot covered, which the next credits pay first.
     */
    public function debt(): int
    {
        return $this->debt;
    }

    /**
     * Pays the debt first, then opens a lot with the rest — empty when the debt took it all.
     */
    private function credit(Movement $movement): void
    {
        $paid = min($this->debt, $movement->amount);
        $this->debt -= $paid;
        $this->lots[] = new Lot($movement->key, $movement->occurredAt, $movement->amount - $paid, $this->currency->expiresAt($movement->occurredAt));
    }

    private function debit(int $amount, ?MovementKey $target, DateTimeImmutable $moment): void
    {
        foreach ($this->lots as $index => $lot) {
            if ($target instanceof MovementKey && $lot->key->equals($target)) {
                $amount = $this->takeFrom($index, $amount);
            }
        }

        foreach ([true, false] as $liveOnly) {
            foreach ($this->sortedIndexes() as $index) {
                if (!$liveOnly || $this->lots[$index]->isLiveAt($moment)) {
                    $amount = $this->takeFrom($index, $amount);
                }
            }
        }

        $this->debt += $amount;
    }

    /**
     * Takes what it can of the amount from the lot; returns what is left to take.
     */
    private function takeFrom(int $index, int $amount): int
    {
        $taken = min($amount, $this->lots[$index]->remaining);
        $this->lots[$index] = $this->lots[$index]->take($taken);

        return $amount - $taken;
    }

    /**
     * @return list<Lot>
     */
    private function sorted(): array
    {
        return array_map(fn (int $index): Lot => $this->lots[$index], $this->sortedIndexes());
    }

    /**
     * The indexes of the lots, oldest credit first; credits of the same moment in the order they were written.
     *
     * @return list<int>
     */
    private function sortedIndexes(): array
    {
        $indexes = array_keys($this->lots);
        usort($indexes, fn (int $a, int $b): int => [$this->lots[$a]->creditedAt, $a] <=> [$this->lots[$b]->creditedAt, $b]);

        return $indexes;
    }
}
