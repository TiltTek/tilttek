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
use Tilttek\Exchange\Exception\ReservationPending;
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
     * The reservation is written `Paying`, which nothing confirms, cancels or expires before its lifetime ends, then
     * becomes `Reserved` once its points are spent. When it was settled meanwhile — its lifetime ended while the
     * payment ran —, the points just spent come back, and the reservation is returned as it stands.
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

        $reservation = new Reservation($key, $member, $code, $quantity, $reward->cost($quantity), $this->currencies->get($reward->currency)->code, $now, $now->add($this->lifetime), ReservationStatus::Paying);

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
     * @throws ExchangeClosed     when the reservation was cancelled or has expired — its points are back with the
     *                            member
     * @throws ReservationPending when its points are being paid
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
     * @throws ExchangeClosed     when the reservation was confirmed or has expired
     * @throws ReservationPending when its points are being paid
     */
    public function cancel(string $key): Reservation
    {
        return $this->settle($key, ReservationStatus::Cancelled);
    }

    /**
     * Expires the reservations whose lifetime has ended, giving their points back: the task a scheduler runs. Returns
     * how many expired. A reservation still paying at the end of its lifetime expires too: a payment that was still
     * running gives its points back itself.
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

        return ReservationStatus::Paying === $existing->status ? $this->pay($existing) : $existing;
    }

    /**
     * Spends the reservation's points, once, then moves it from `Paying` to `Reserved`: a reservation whose points
     * cannot be paid is discarded, and its units are free again; one settled or discarded meanwhile gets its points
     * back.
     */
    private function pay(Reservation $reservation): Reservation
    {
        try {
            $this->points->spend($reservation->member, $reservation->amount, $reservation->spendingKey(), $reservation->redemption(), $reservation->currency);
        } catch (InsufficientPoints $insufficientPoints) {
            $this->reservations->discard($reservation->key);

            throw $insufficientPoints;
        }

        $now = $this->clock->now();

        if ($this->reservations->transition($reservation->key, ReservationStatus::Paying, ReservationStatus::Reserved, $now)) {
            $this->announcer->announce(new RewardReserved($reservation->settled(ReservationStatus::Reserved, $now)));
        }

        // As it stands now: reserved by this call or by another process that paid it, or settled once its lifetime
        // ended, or discarded. The points of a reservation settled without them, or gone, come back.
        $current = $this->reservations->find($reservation->key);

        if (!$current instanceof Reservation || !\in_array($current->status, ReservationStatus::holding(), true)) {
            $this->refund($reservation);
        }

        return $current ?? throw ExchangeClosed::because(\sprintf('The reservation "%s" was withdrawn while its points were paid; they are back with the member.', $reservation->key->value));
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
        $due = $reservation->isDue($this->clock->now());

        if (ReservationStatus::Paying === $reservation->status && !$due) {
            throw ReservationPending::of($key);
        }

        $this->close($reservation, $due ? ReservationStatus::Expired : $status);

        $current = $this->get($key);

        if ($status === $current->status) {
            return $current;
        }

        if ($current->status->isOpen()) {
            // Its payment ended between the read and the write: settle it as it is now.
            return $this->settle($key, $status);
        }

        throw ExchangeClosed::because(\sprintf('The reservation "%s" is %s; it cannot be %s.', $key, $current->status->value, $status->value));
    }

    /**
     * Settles the reservation if it is still in the status it was read in, gives the points back unless it is
     * confirmed, and tells it; returns whether this call settled it.
     */
    private function close(Reservation $reservation, ReservationStatus $status): bool
    {
        $now = $this->clock->now();

        if (!$reservation->status->isOpen() || !$this->reservations->transition($reservation->key, $reservation->status, $status, $now)) {
            return false;
        }

        $settled = $reservation->settled($status, $now);

        if (ReservationStatus::Confirmed !== $status) {
            $this->refund($reservation);
        }

        $this->announcer->announce(match ($status) {
            ReservationStatus::Confirmed => new ReservationConfirmed($settled),
            ReservationStatus::Cancelled => new ReservationCancelled($settled),
            default => new ReservationExpired($settled),
        });

        return true;
    }

    /**
     * Gives the reservation's points back, once.
     */
    private function refund(Reservation $reservation): void
    {
        try {
            $this->points->refund($reservation->spendingKey());
        } catch (UnknownMovement) {
            // The points of a reservation settled before its spending was written were never taken; the payment, if
            // it still runs, gives them back itself.
        }
    }
}
