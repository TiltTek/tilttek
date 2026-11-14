<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateInterval;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\LedgerContention;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Exception\UnknownCurrency;
use Tilttek\Points\Exception\UnknownMovement;
use Tilttek\Points\Ledger\Ledger;

/**
 * The points of the members, in one object: balances and histories, the movements the application decides — grants,
 * revocations, spendings, refunds, transfers —, the expiry, and the erasure of a member. The points earned from the
 * activity are written by `PointsProjection`.
 *
 * ```php
 * $points->balance($member);                                        // 120
 * $points->spend($member, 100, 'order:2026-0193:redeem', 'order:2026-0193');
 * $points->transfer($member, $friend, 10, 'gift:2026-11-07:44');
 * ```
 *
 * Each operation the application decides takes its key: the same call made twice moves the points once. A spending and
 * a transfer never make a balance negative, and the credits due to expire are expired before they are checked.
 */
final readonly class Points
{
    public const int MAX_HISTORY = 1000;

    public const int DEFAULT_BATCH_SIZE = 500;

    /**
     * How many times the expiry reads the lots again when another movement of the account is written meanwhile.
     */
    public const int EXPIRY_ATTEMPTS = 5;

    public function __construct(
        private Ledger $ledger,
        private ClockInterface $clock,
        private Currencies $currencies,
        private int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        if ($batchSize < 1) {
            throw InvalidPoints::because(\sprintf('A batch holds at least one entry; %d is not a batch size.', $batchSize));
        }
    }

    /**
     * The member's balance in the currency, the default one when none is named.
     *
     * @throws UnknownCurrency
     */
    public function balance(ResourceReference $member, ?string $currency = null): int
    {
        return $this->ledger->balance(Account::member($member), $this->currencies->get($currency)->code);
    }

    /**
     * The member's movements after a position, in the currency or in all of them, oldest first.
     *
     * @return list<LedgerEntry>
     *
     * @throws InvalidPoints when the position is negative or the limit is not 1 to 1000
     */
    public function history(ResourceReference $member, ?string $currency = null, int $after = 0, int $limit = 50): array
    {
        if ($limit > self::MAX_HISTORY) {
            throw InvalidPoints::because(\sprintf('A history holds at most %d movements; %d is too many.', self::MAX_HISTORY, $limit));
        }

        return $this->ledger->statement(Account::member($member), null === $currency ? null : $this->currencies->get($currency)->code, $after, $limit);
    }

    /**
     * Gives the member points by hand, issued from the source: a contest won, a correction, a welcome gift.
     *
     * @throws MovementConflict
     */
    public function grant(ResourceReference $member, int $amount, string $key, string $source = 'manual', ?string $currency = null): Recorded
    {
        return $this->ledger->append($this->decided($key, MovementKind::Granted, $currency, Account::issuance($source), Account::member($member), $amount), $this->clock->now());
    }

    /**
     * Takes points back by hand, to the source: a correction recorded as it is, even when it leaves the balance below
     * zero, since it is no exchange.
     *
     * @throws MovementConflict
     */
    public function revoke(ResourceReference $member, int $amount, string $key, string $source = 'manual', ?string $currency = null): Recorded
    {
        return $this->ledger->append($this->decided($key, MovementKind::Revoked, $currency, Account::member($member), Account::issuance($source), $amount), $this->clock->now());
    }

    /**
     * Spends the member's points on a redemption — a reward, a discount, a reservation —, named like `reward:12`.
     *
     * @throws InsufficientPoints when the balance, once the credits due have expired, does not cover the amount
     * @throws MovementConflict
     */
    public function spend(ResourceReference $member, int $amount, string $key, string $redemption, ?string $currency = null): Recorded
    {
        $movement = $this->decided($key, MovementKind::Spent, $currency, Account::member($member), Account::redemption($redemption), $amount);
        $this->expire($member, $movement->currency);

        return $this->ledger->appendCovered($movement, $this->clock->now());
    }

    /**
     * Gives back the points of a spending, once: a reservation cancelled, a reward that could not be delivered. The
     * points come back as a new credit, whose lifetime starts now.
     *
     * @throws UnknownMovement when no spending is written under the key
     */
    public function refund(string $spendingKey): Recorded
    {
        $spending = $this->ledger->find(MovementKey::decided($spendingKey))?->movement;

        if (!$spending instanceof Movement || MovementKind::Spent !== $spending->kind) {
            throw UnknownMovement::because(\sprintf('No spending is written under the key "%s".', $spendingKey));
        }

        return $this->ledger->append(new Movement(
            MovementKey::derived('refund', $spendingKey),
            MovementKind::Refunded,
            $spending->currency,
            $spending->credit,
            $spending->debit,
            $spending->amount,
            $this->clock->now(),
            reverses: $spending->key,
        ), $this->clock->now());
    }

    /**
     * Gives points from one member to another, when the application allows it.
     *
     * @throws InsufficientPoints when the giver's balance, once the credits due have expired, does not cover the amount
     * @throws MovementConflict
     */
    public function transfer(ResourceReference $from, ResourceReference $to, int $amount, string $key, ?string $currency = null): Recorded
    {
        $movement = $this->decided($key, MovementKind::Transferred, $currency, Account::member($from), Account::member($to), $amount);
        $this->expire($from, $movement->currency);

        return $this->ledger->appendCovered($movement, $this->clock->now());
    }

    /**
     * Expires the member's credits whose lifetime has ended, first in first out, each by a movement of its own dated
     * at the end of its lifetime; returns how many points expired. Nothing expires in a currency without a lifetime.
     *
     * The lots are read from the checkpoint the ledger kept and the entries written after it, and each expiry is
     * written only if no other entry of the account was written since they were read — a spending that slipped in
     * between is applied to the lots, which are read again, so that the points are never taken twice.
     *
     * @throws LedgerContention when the account changed during each attempt
     */
    public function expire(ResourceReference $member, ?string $currency = null): int
    {
        $currency = $this->currencies->get($currency);

        if (!$currency->lifetime instanceof DateInterval) {
            return 0;
        }

        $account = Account::member($member);

        for ($attempt = 1; $attempt <= self::EXPIRY_ATTEMPTS; ++$attempt) {
            $expired = $this->expireLots($account, $currency);

            if (null !== $expired) {
                return $expired;
            }
        }

        throw LedgerContention::on($account, $currency->code, self::EXPIRY_ATTEMPTS);
    }

    /**
     * Expires the credits due of every member, in every currency with a lifetime: the task a scheduler runs. Returns
     * how many points expired.
     */
    public function expireAll(): int
    {
        $expired = 0;

        foreach ($this->currencies->all() as $currency) {
            if (!$currency->lifetime instanceof DateInterval) {
                continue;
            }

            foreach ($this->ledger->membersCreditedBefore($currency->code, $this->clock->now()->sub($currency->lifetime)) as $account) {
                $member = $account->memberReference();

                if ($member instanceof ResourceReference) {
                    $expired += $this->expire($member, $currency->code);
                }
            }
        }

        return $expired;
    }

    /**
     * Erases the member's points: the movements between the member and the ledger's own accounts are deleted, and the
     * member is replaced by `erased:member` in the transfers with other members, which keep their points.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return $this->ledger->eraseMember($member);
    }

    /**
     * Expires the account's lots due in the currency; returns how many points expired, or null when another entry of
     * the account was written meanwhile, to read the lots again.
     */
    private function expireLots(Account $account, Currency $currency): ?int
    {
        $kept = $this->ledger->keptLots($account, $currency->code);
        [$lots, $after] = $this->replay($account, $currency, $kept);

        if ($kept instanceof LotsCheckpoint && !$this->holds($lots, $account, $currency)) {
            // The checkpoint no longer matches the ledger — a movement before it appeared late, or was rewritten.
            $kept = null;
            [$lots, $after] = $this->replay($account, $currency, null);
        }

        if (!$this->holds($lots, $account, $currency)) {
            return null;
        }

        $now = $this->clock->now();
        $expired = 0;

        foreach ($lots->open() as $lot) {
            $expiredAt = $lot->expiredBy($now);

            if ($expiredAt instanceof DateTimeImmutable) {
                $recorded = $this->ledger->appendAfter(new Movement(
                    MovementKey::derived('expire', $lot->key->value),
                    MovementKind::Expired,
                    $currency->code,
                    $account,
                    Account::expiry(),
                    $lot->remaining,
                    $expiredAt,
                    reverses: $lot->key,
                ), $now, $after);

                if (true !== $recorded?->new) {
                    return null;
                }

                $lots->apply($recorded->entry);
                $after = $recorded->entry->position;
                $expired += $lot->remaining;
            }
        }

        if ($after > ($kept->position ?? 0)) {
            $this->ledger->keepLots($lots->checkpoint($after));
        }

        return $expired;
    }

    /**
     * The account's lots in the currency, from the checkpoint or from the start, with the position of the last entry
     * applied.
     *
     * @return array{Lots, int}
     */
    private function replay(Account $account, Currency $currency, ?LotsCheckpoint $kept): array
    {
        $lots = $kept instanceof LotsCheckpoint ? Lots::resume($kept, $currency) : new Lots($account, $currency);
        $after = $kept->position ?? 0;

        do {
            $batch = $this->ledger->statement($account, $currency->code, $after, $this->batchSize);

            foreach ($batch as $entry) {
                $lots->apply($entry);
                $after = $entry->position;
            }
        } while (\count($batch) === $this->batchSize);

        return [$lots, $after];
    }

    /**
     * Whether the lots stand for the account's balance in the ledger: when they do not, an entry was written after
     * they were read, or one they rest on was not there.
     */
    private function holds(Lots $lots, Account $account, Currency $currency): bool
    {
        return $lots->balance() === $this->ledger->balance($account, $currency->code);
    }

    private function decided(string $key, MovementKind $kind, ?string $currency, Account $debit, Account $credit, int $amount): Movement
    {
        return new Movement(MovementKey::decided($key), $kind, $this->currencies->get($currency)->code, $debit, $credit, $amount, $this->clock->now());
    }
}
