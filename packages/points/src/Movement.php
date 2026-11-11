<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateTimeImmutable;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * Points moving from one account to another: the debited account gives them, the credited account receives them.
 * Every movement has both sides, so a balance is always a sum and points never appear or vanish.
 *
 * The moment is when the movement counts — the moment of the activity for points earned and reversed, so that a
 * reversal frees the cap of the period the activity counted in; the end of a credit's lifetime for points expired;
 * the moment it was asked for otherwise. The cause is the idempotency key of the activity that earned or reversed the
 * points; `reverses` names the movement this one undoes or the credit it expires.
 */
final readonly class Movement
{
    public const int MAX_AMOUNT = 1_000_000_000_000_000;

    public function __construct(
        public MovementKey $key,
        public MovementKind $kind,
        public string $currency,
        public Account $debit,
        public Account $credit,
        public int $amount,
        public DateTimeImmutable $occurredAt,
        public ?string $cause = null,
        public ?MovementKey $reverses = null,
    ) {
        Currency::checkCode($currency);

        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw InvalidPoints::because(\sprintf('A movement moves 1 to %d points; this one moves %d.', self::MAX_AMOUNT, $amount));
        }

        if ($debit->equals($credit)) {
            throw InvalidPoints::because(\sprintf('A movement goes from one account to another; "%s" is on both sides.', $debit->toString()));
        }
    }

    /**
     * Whether the other movement is the same one: the same kind, currency, accounts and amount. The moment is left
     * out, since an operation asked again may stamp it again.
     */
    public function sameAs(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->currency === $other->currency
            && $this->debit->equals($other->debit)
            && $this->credit->equals($other->credit)
            && $this->amount === $other->amount;
    }

    /**
     * What the movement does to the account's balance: the amount when it credits the account, its opposite when it
     * debits it, 0 when the account is on neither side.
     */
    public function amountFor(Account $account): int
    {
        return match (true) {
            $this->credit->equals($account) => $this->amount,
            $this->debit->equals($account) => -$this->amount,
            default => 0,
        };
    }
}
