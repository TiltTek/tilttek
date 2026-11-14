<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateTimeImmutable;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * What is left of each credit to a member's account in one currency once the ledger's entries up to a position are
 * applied: the lots with points left, oldest first, and the debt. The expiry keeps it in the ledger and starts from it
 * the next time, reading only the entries written after the position instead of the whole history.
 *
 * A lot's end is not kept: it is computed again from the moment of the credit and the currency's lifetime.
 */
final readonly class LotsCheckpoint
{
    private const string MOMENT = 'Y-m-d\TH:i:s.uP';

    /**
     * @param list<Lot> $lots the lots with points left, oldest first
     */
    public function __construct(
        public Account $account,
        public string $currency,
        public int $position,
        public array $lots = [],
        public int $debt = 0,
    ) {
        Currency::checkCode($currency);

        if ($position < 1 || $debt < 0) {
            throw InvalidPoints::because(\sprintf('A checkpoint stands at a position from 1 with a debt from 0; %d and %d are not.', $position, $debt));
        }

        foreach ($lots as $lot) {
            if ($lot->remaining < 1) {
                throw InvalidPoints::because(\sprintf('A checkpoint keeps the lots with points left; "%s" has none.', $lot->key->value));
            }
        }
    }

    /**
     * The checkpoint as `toArray()` wrote it, for the account and the currency at the position.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidPoints when the data does not read as a checkpoint
     */
    public static function fromArray(Account $account, string $currency, int $position, array $data): self
    {
        $debt = $data['debt'] ?? null;
        $lots = $data['lots'] ?? null;

        if (!\is_int($debt) || !\is_array($lots)) {
            throw InvalidPoints::because('A checkpoint holds its debt and its lots.');
        }

        return new self($account, $currency, $position, array_values(array_map(self::lot(...), $lots)), $debt);
    }

    /**
     * The checkpoint as plain values, for a ledger that stores it as JSON.
     *
     * @return array{debt: int, lots: list<array{key: string, credited_at: string, remaining: int}>}
     */
    public function toArray(): array
    {
        return [
            'debt' => $this->debt,
            'lots' => array_map(static fn (Lot $lot): array => ['key' => $lot->key->value, 'credited_at' => $lot->creditedAt->format(self::MOMENT), 'remaining' => $lot->remaining], $this->lots),
        ];
    }

    private static function lot(mixed $lot): Lot
    {
        $key = \is_array($lot) ? $lot['key'] ?? null : null;
        $creditedAt = \is_array($lot) ? $lot['credited_at'] ?? null : null;
        $remaining = \is_array($lot) ? $lot['remaining'] ?? null : null;

        if (!\is_string($key) || !\is_string($creditedAt) || !\is_int($remaining)) {
            throw InvalidPoints::because('A lot of a checkpoint holds its key, the moment of its credit and what is left of it.');
        }

        $moment = DateTimeImmutable::createFromFormat('!'.self::MOMENT, $creditedAt);

        if (!$moment instanceof DateTimeImmutable || $moment->format(self::MOMENT) !== $creditedAt) {
            throw InvalidPoints::because(\sprintf('"%s" is not the moment of a credit, as a checkpoint writes it.', $creditedAt));
        }

        return new Lot(MovementKey::fromString($key), $moment, $remaining, null);
    }
}
