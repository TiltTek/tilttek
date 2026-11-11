<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Transfer;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Points\Movement;

/**
 * Points one member gave another, as the journal of transfers keeps it: its key, the giver, the receiver, the amount,
 * the currency and the moment. Its points are a transfer of the ledger under `ledgerKey()`.
 */
final readonly class Transfer
{
    public function __construct(
        public ExchangeKey $key,
        public ResourceReference $from,
        public ResourceReference $to,
        public int $amount,
        public string $currency,
        public DateTimeImmutable $at,
    ) {
        if ($amount < 1 || $amount > Movement::MAX_AMOUNT) {
            throw InvalidExchange::because(\sprintf('A transfer gives 1 to %d points; "%s" gives %d.', Movement::MAX_AMOUNT, $key->value, $amount));
        }
    }

    /**
     * Whether the other transfer is the same one: the same giver, receiver, amount and currency. The moment is left
     * out, since a transfer asked again may stamp it again.
     */
    public function sameAs(self $other): bool
    {
        return $this->from->equals($other->from)
            && $this->to->equals($other->to)
            && $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    /**
     * The key of the ledger's transfer that moved the points.
     */
    public function ledgerKey(): string
    {
        return 'exchange:transfer:'.$this->key->value;
    }

    /**
     * The transfer as plain values, for an export or the journal.
     *
     * @return array{key: string, from: string, to: string, amount: int, currency: string, at: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'from' => $this->from->toString(),
            'to' => $this->to->toString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'at' => $this->at->format(DateTimeInterface::ATOM),
        ];
    }
}
