<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Transfer;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Exchange\Announcer;
use Tilttek\Exchange\Event\PointsTransferred;
use Tilttek\Exchange\Exception\ExchangeConflict;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\SilentAnnouncer;
use Tilttek\Points\Currencies;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\UnknownCurrency;
use Tilttek\Points\Points;

/**
 * Points members give each other, when the application allows it, within the guards: never to oneself, not from or
 * to an account younger than the delay, and no more than the allowance over its period. Every transfer is written to
 * a journal the application examines.
 *
 * ```php
 * $transfers->transfer($member, $friend, 50, 'gift:2026-11-07:44');
 * ```
 */
final readonly class Transfers
{
    public const int MAX_JOURNAL = 1000;

    public function __construct(
        private TransferLog $log,
        private Points $points,
        private Currencies $currencies,
        private ClockInterface $clock,
        private Guards $guards = new Guards(),
        private Announcer $announcer = new SilentAnnouncer(),
    ) {
    }

    /**
     * Gives points from one member to another. The same key given again returns the same transfer.
     *
     * @throws ExchangeRefused    when the guards refuse it
     * @throws InsufficientPoints when the giver's balance does not cover the amount
     * @throws ExchangeConflict   when the key is written for another transfer
     * @throws UnknownCurrency
     */
    public function transfer(ResourceReference $from, ResourceReference $to, int $amount, string $key, ?string $currency = null): Transfer
    {
        $now = $this->clock->now();
        $transfer = new Transfer(ExchangeKey::fromString($key), $from, $to, $amount, $this->currencies->get($currency)->code, $now);
        $existing = $this->log->find($transfer->key);

        if ($existing instanceof Transfer && !$existing->sameAs($transfer)) {
            throw ExchangeConflict::on('transfer', $key);
        }

        if (!$existing instanceof Transfer) {
            $this->guards->checkParties($from, $to, $now);
            [$since, $until] = $this->guards->window($now);
            $this->guards->checkAllowance($from, $this->log->given($from, $transfer->currency, $since, $until), $amount);
        }

        $this->points->transfer($from, $to, $amount, $transfer->ledgerKey(), $transfer->currency);

        if ($this->log->record($transfer)) {
            $this->announcer->announce(new PointsTransferred($transfer));
        }

        return $existing ?? $transfer;
    }

    /**
     * What the member gave in the currency over the allowance's current period, or ever without an allowance.
     *
     * @throws UnknownCurrency
     */
    public function given(ResourceReference $member, ?string $currency = null): int
    {
        [$since, $until] = $this->guards->window($this->clock->now());

        return $this->log->given($member, $this->currencies->get($currency)->code, $since, $until);
    }

    /**
     * The latest transfers of every member, newest first: the journal the application examines.
     *
     * @return list<Transfer>
     */
    public function journal(int $limit = 50): array
    {
        return $this->log->latest(max(1, min($limit, self::MAX_JOURNAL)));
    }

    /**
     * The transfers the member gave or received, oldest first.
     *
     * @return list<Transfer>
     */
    public function ofMember(ResourceReference $member): array
    {
        return $this->log->ofMember($member);
    }
}
