<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reservation;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Exchange\Announcer;
use Tilttek\Exchange\Event\ReservationCancelled;
use Tilttek\Exchange\Event\ReservationConfirmed;
use Tilttek\Exchange\Event\ReservationExpired;
use Tilttek\Exchange\Event\RewardReserved;
use Tilttek\Exchange\Exception\ExchangeClosed;
use Tilttek\Exchange\Exception\ExchangeConflict;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Exception\UnknownExchange;
use Tilttek\Exchange\Exception\UnknownReward;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Reward\Catalog;
use Tilttek\Exchange\Reward\NoRequirements;
use Tilttek\Exchange\Reward\Requirements;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Exchange\SilentAnnouncer;
use Tilttek\Points\Currencies;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\UnknownMovement;
use Tilttek\Points\Points;

/**
 * Rewards exchanged for points, in two steps: a reservation spends the member's points and holds the units, then the
 * application confirms once it has delivered — a code sent, a discount applied — or cancels, and the points come
 * back. A reservation left unconfirmed expires after its lifetime, and its points come back too.
 *
 * ```php
 * $reservation = $redemptions->reserve($member, 'discount-10', 'order:2026-0193:reward');
 * // … the application delivers, on the RewardReserved event or right away …
 * $redemptions->confirm('order:2026-0193:reward');
 * ```
 *
 * Each step is safe to run twice: the same key reserves once, a confirmed reservation stays confirmed, a cancelled one
 * gives its points back once. Delivering is the application's affair, and points are never turned into money.
 */
final readonly class Redemptions
{
    public const string DEFAULT_LIFETIME = 'PT30M';

    public const int DEFAULT_BATCH_SIZE = 500;

    private DateInterval $lifetime;

    /**
     * @param string $lifetime how long a reservation waits for its confirmation, as an ISO 8601 duration
     */
    public function __construct(
        private Catalog $catalog,
        private ReservationStore $reservations,
        private Points $points,
        private Currencies $currencies,
        private ClockInterface $clock,
        private Requirements $requirements = new NoRequirements(),
        private Announcer $announcer = new SilentAnnouncer(),
        string $lifetime = self::DEFAULT_LIFETIME,
    ) {
        try {
            $this->lifetime = new DateInterval($lifetime);
        } catch (Exception) {
            throw InvalidExchange::because(\sprintf('"%s" is not an ISO 8601 duration like "PT30M".', $lifetime));
        }

        $now = new DateTimeImmutable();

        if ($now->add($this->lifetime) <= $now) {
            throw InvalidExchange::because(\sprintf('A reservation waits for some time; "%s" is no time.', $lifetime));
        }
    }

    /**
     * Why the member cannot reserve the units of the reward now — its period, a condition, the stock, the member's
     * limit —; null when they can, the balance aside.
     *
     * @throws UnknownReward
     */
    public function refusal(ResourceReference $member, string $code, int $quantity = 1): ?Refusal
    {
        $reward = $this->catalog->get($code);
        $reward->cost($quantity);

        return $reward->availability($this->clock->now()) ?? match (true) {
            null !== $this->unmet($member, $reward) => Refusal::RequirementUnmet,
            null !== $reward->stock && $this->reservations->held($code) + $quantity > $reward->stock => Refusal::OutOfStock,
            null !== $reward->perMember && $this->reservations->held($code, $member) + $quantity > $reward->perMember => Refusal::MemberLimitReached,
            default => null,
        };
    }

    /**
     * The units of the reward left to reserve; null when its units never run out.
     *
     * @throws UnknownReward
     */
    public function remaining(string $code): ?int
    {
        $stock = $this->catalog->get($code)->stock;

        return null === $stock ? null : max(0, $stock - $this->reservations->held($code));
    }

    /**
     * Reserves units of the reward for the member: the points are spent and the units held until the application
     * confirms or cancels, or the reservation expires. The same key given again returns the same reservation.
     *
     * @throws UnknownReward
     * @throws ExchangeRefused    when the reward is not available, a condition is unmet, the stock or the member's
     *                            limit is reached
     * @throws InsufficientPoints when the member's balance does not cover the cost; nothing is held
     * @throws ExchangeConflict   when the key is written for another reservation
     */
    public function reserve(ResourceReference $member, string $code, string $key, int $quantity = 1): Reservation
    {
        $key = ExchangeKey::fromString($key);
        $existing = $this->reservations->find($key);

        if ($existing instanceof Reservation) {
            return $this->resume($existing, $member, $code, $quantity);
        }

        $reward = $this->catalog->get($code);
        $now = $this->clock->now();
        $availability = $reward->availability($now);

        if ($availability instanceof Refusal) {
            throw ExchangeRefused::because($availability, \sprintf('The reward "%s" is not available at this moment.', $code));
        }

        $unmet = $this->unmet($member, $reward);

        if (null !== $unmet) {
            throw ExchangeRefused::because(Refusal::RequirementUnmet, \sprintf('"%s" does not meet "%s", which the reward "%s" requires.', $member->toString(), $unmet, $code));
        }

        $reservation = new Reservation($key, $member, $code, $quantity, $reward->cost($quantity), $this->currencies->get($reward->currency)->code, $now, $now->add($this->lifetime));

        if (!$this->reservations->add($reservation, $reward->stock, $reward->perMember)) {
            return $this->resume($this->reservations->find($key) ?? $reservation, $member, $code, $quantity);
        }

        return $this->pay($reservation);
    }

    /**
     * Confirms the reservation once the application has delivered: the points stay spent. Confirming it again
     * returns it as it is.
     *
     * @throws UnknownExchange
     * @throws ExchangeClosed  when the reservation was cancelled or has expired — its points are back with the member
     */
    public function confirm(string $key): Reservation
    {
        return $this->settle($key, ReservationStatus::Confirmed);
    }

    /**
     * Cancels the reservation: the points come back to the member and the units to the stock. Cancelling it again
     * returns it as it is.
     *
     * @throws UnknownExchange
     * @throws ExchangeClosed  when the reservation was confirmed or has expired
     */
    public function cancel(string $key): Reservation
    {
        return $this->settle($key, ReservationStatus::Cancelled);
    }

    /**
     * Expires the reservations whose lifetime has ended, giving their points back: the task a scheduler runs. Returns
     * how many expired.
     */
    public function expireDue(int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $expired = 0;

        do {
            $due = $this->reservations->due($this->clock->now(), $batchSize);

            foreach ($due as $reservation) {
                if ($this->close($reservation, ReservationStatus::Expired)) {
                    ++$expired;
                }
            }
        } while (\count($due) === $batchSize);

        return $expired;
    }

    /**
     * @throws UnknownExchange
     */
    public function get(string $key): Reservation
    {
        return $this->reservations->find(ExchangeKey::fromString($key)) ?? throw UnknownExchange::reservation($key);
    }

    /**
     * The member's reservations, in the order they were made.
     *
     * @return list<Reservation>
     */
    public function ofMember(ResourceReference $member): array
    {
        return $this->reservations->ofMember($member);
    }

    private function resume(Reservation $existing, ResourceReference $member, string $code, int $quantity): Reservation
    {
        if (!$existing->asksFor($member, $code, $quantity)) {
            throw ExchangeConflict::on('reservation', $existing->key->value);
        }

        return ReservationStatus::Reserved === $existing->status ? $this->pay($existing) : $existing;
    }

    /**
     * Spends the reservation's points, once: a reservation whose points cannot be paid is discarded, and its units
     * are free again.
     */
    private function pay(Reservation $reservation): Reservation
    {
        try {
            $spent = $this->points->spend($reservation->member, $reservation->amount, $reservation->spendingKey(), $reservation->redemption(), $reservation->currency);
        } catch (InsufficientPoints $insufficientPoints) {
            $this->reservations->discard($reservation->key);

            throw $insufficientPoints;
        }

        if ($spent->new) {
            $this->announcer->announce(new RewardReserved($reservation));
        }

        return $reservation;
    }

    /**
     * The first condition of the reward the member does not meet; null when they meet them all.
     */
    private function unmet(ResourceReference $member, Reward $reward): ?string
    {
        foreach ($reward->requires as $requirement) {
            if (!$this->requirements->meets($member, $requirement)) {
                return $requirement;
            }
        }

        return null;
    }

    private function settle(string $key, ReservationStatus $status): Reservation
    {
        $reservation = $this->get($key);

        $this->close($reservation, $reservation->isDue($this->clock->now()) ? ReservationStatus::Expired : $status);

        $current = $this->get($key);

        if ($status === $current->status) {
            return $current;
        }

        throw ExchangeClosed::because(\sprintf('The reservation "%s" is %s; it cannot be %s.', $key, $current->status->value, $status->value));
    }

    /**
     * Settles the reservation if it is still reserved, gives the points back unless it is confirmed, and tells it;
     * returns whether this call settled it.
     */
    private function close(Reservation $reservation, ReservationStatus $status): bool
    {
        $now = $this->clock->now();

        if (!$this->reservations->settle($reservation->key, $status, $now)) {
            return false;
        }

        $settled = $reservation->settled($status, $now);

        if (ReservationStatus::Confirmed !== $status) {
            try {
                $this->points->refund($reservation->spendingKey());
            } catch (UnknownMovement) {
                // The points of a reservation interrupted before its spending was written were never taken.
            }
        }

        $this->announcer->announce(match ($status) {
            ReservationStatus::Confirmed => new ReservationConfirmed($settled),
            ReservationStatus::Cancelled => new ReservationCancelled($settled),
            default => new ReservationExpired($settled),
        });

        return true;
    }
}
