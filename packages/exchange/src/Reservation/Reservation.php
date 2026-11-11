<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reservation;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;

/**
 * A member's reservation of a reward: the units, the points they cost in a currency, when it was made and when it
 * expires unless confirmed, where it stands and when it was settled. Its points are a spending of the ledger under
 * `spendingKey()`, given back when the reservation is cancelled or expires.
 */
final readonly class Reservation
{
    public function __construct(
        public ExchangeKey $key,
        public ResourceReference $member,
        public string $reward,
        public int $quantity,
        public int $amount,
        public string $currency,
        public DateTimeImmutable $reservedAt,
        public DateTimeImmutable $expiresAt,
        public ReservationStatus $status = ReservationStatus::Reserved,
        public ?DateTimeImmutable $settledAt = null,
    ) {
        if ($quantity < 1 || $amount < 1) {
            throw InvalidExchange::because(\sprintf('A reservation holds at least one unit for at least one point; "%s" holds %d for %d.', $key->value, $quantity, $amount));
        }

        if ($expiresAt <= $reservedAt) {
            throw InvalidExchange::because(\sprintf('The reservation "%s" expires before it is made.', $key->value));
        }

        if ((ReservationStatus::Reserved === $status) !== (!$settledAt instanceof DateTimeImmutable)) {
            throw InvalidExchange::because(\sprintf('The reservation "%s" is settled when, and only when, it is no longer reserved.', $key->value));
        }
    }

    /**
     * Whether the reservation is the one asked for: the same member, reward and quantity.
     */
    public function asksFor(ResourceReference $member, string $reward, int $quantity): bool
    {
        return $this->member->equals($member) && $this->reward === $reward && $this->quantity === $quantity;
    }

    /**
     * Whether the reservation is still reserved past its lifetime.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        return ReservationStatus::Reserved === $this->status && $this->expiresAt <= $now;
    }

    /**
     * The reservation settled: confirmed, cancelled or expired at the moment.
     */
    public function settled(ReservationStatus $status, DateTimeImmutable $at): self
    {
        return new self($this->key, $this->member, $this->reward, $this->quantity, $this->amount, $this->currency, $this->reservedAt, $this->expiresAt, $status, $at);
    }

    /**
     * The redemption account of the ledger the points go to: `reward:` and the reward's code.
     */
    public function redemption(): string
    {
        return 'reward:'.$this->reward;
    }

    /**
     * The key of the spending of the ledger that holds the reservation's points.
     */
    public function spendingKey(): string
    {
        return 'exchange:reservation:'.$this->key->value;
    }

    /**
     * The reservation as plain values, for an export or a log.
     *
     * @return array{key: string, member: string, reward: string, quantity: int, amount: int, currency: string, status: string, reserved_at: string, expires_at: string, settled_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'member' => $this->member->toString(),
            'reward' => $this->reward,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'reserved_at' => $this->reservedAt->format(DateTimeInterface::ATOM),
            'expires_at' => $this->expiresAt->format(DateTimeInterface::ATOM),
            'settled_at' => $this->settledAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
