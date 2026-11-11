<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * A movement as the ledger keeps it: its position, which only grows, the movement, and when the ledger wrote it.
 */
final readonly class LedgerEntry
{
    public function __construct(
        public int $position,
        public Movement $movement,
        public DateTimeImmutable $at,
    ) {
        if ($position < 1) {
            throw InvalidPoints::because(\sprintf('A position in the ledger starts at 1; %d is not one.', $position));
        }
    }

    /**
     * The entry as plain values, for an export, a history or a log.
     *
     * @return array{position: int, key: string, kind: string, currency: string, debit: string, credit: string, amount: int, occurred_at: string, written_at: string, cause: ?string, reverses: ?string}
     */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'key' => $this->movement->key->value,
            'kind' => $this->movement->kind->value,
            'currency' => $this->movement->currency,
            'debit' => $this->movement->debit->toString(),
            'credit' => $this->movement->credit->toString(),
            'amount' => $this->movement->amount,
            'occurred_at' => $this->movement->occurredAt->format(DateTimeInterface::ATOM),
            'written_at' => $this->at->format(DateTimeInterface::ATOM),
            'cause' => $this->movement->cause,
            'reverses' => $this->movement->reverses?->value,
        ];
    }
}
