<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reservation;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;

/**
 * Where the reservations are kept. A reservation is added once per key, its units checked against the stock and the
 * member's limit in the same step that writes it, so that two members never get the last unit. Its status changes by
 * a compare-and-set: it leaves `Paying` once, so that its points are paid before anything settles it, and leaves
 * `Reserved` once, so that two processes never both confirm, cancel or expire it.
 */
interface ReservationStore
{
    /**
     * Adds the reservation, as it is given — `Paying` —, if its units fit in the stock and in the member's limit,
     * counting the units of the reward's paying, reserved and confirmed reservations; returns false, writing nothing,
     * when the key is already there.
     *
     * @throws ExchangeRefused with `Refusal::OutOfStock` or `Refusal::MemberLimitReached`
     */
    public function add(Reservation $reservation, ?int $stock, ?int $perMember): bool;

    public function find(ExchangeKey $key): ?Reservation;

    /**
     * Moves the reservation to the status if it is still in `from`, checked and written as one step that another
     * process cannot slip between — `Paying` to `Reserved` once its points are paid, or an open status to `Confirmed`,
     * `Cancelled` or `Expired`, settled at the moment —; returns whether this call moved it.
     */
    public function transition(ExchangeKey $key, ReservationStatus $from, ReservationStatus $to, DateTimeImmutable $at): bool;

    /**
     * Deletes a reservation still paying, whose points could not be paid.
     */
    public function discard(ExchangeKey $key): void;

    /**
     * The units of the reward that its paying, reserved and confirmed reservations hold, of every member or of one.
     */
    public function held(string $reward, ?ResourceReference $member = null): int;

    /**
     * The reservations still paying or reserved whose lifetime ended at the moment or before, the oldest first, at
     * most `limit` of them.
     *
     * @return list<Reservation>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /**
     * The member's reservations, in the order they were made.
     *
     * @return list<Reservation>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Deletes the member's reservations; returns how many it deleted.
     */
    public function eraseMember(ResourceReference $member): int;
}
