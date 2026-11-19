<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * The points a reserved reward holds, in three moves: held when the reward is reserved, kept once the application has
 * delivered it, given back when the reservation is cancelled, refused or expires. Each move is keyed by the
 * reservation, so it is safe to run twice, and the available balance leaves out what is held and not yet settled. The
 * caller settles a reservation once, confirmed or released and never both, as the reservation store's `transition()`
 * makes sure.
 *
 * The bundle's implementation, `SpendingPointsHold`, holds by spending the points at once and gives them back by a
 * refund, the moves `Redemptions` writes in the ledger: under the same reservation key, a hold and a reservation are
 * the same spending. An implementation on pending entries of the ledger replaces it without changing the callers.
 */
interface PointsHold
{
    /**
     * Holds the reservation's amount out of the member's available balance. Returns true when this call held it, false
     * when the reservation's points were already held.
     *
     * @throws InsufficientPoints when the available balance does not cover the amount; nothing is held
     */
    public function hold(Reservation $reservation): bool;

    /**
     * Keeps the held points spent, once the reward is delivered. Confirming again, or confirming points that were
     * never held, changes nothing.
     */
    public function confirm(Reservation $reservation): void;

    /**
     * Gives the held points back to the member, when the reservation is cancelled, refused or expires. Releasing
     * again, or releasing points that were never held, changes nothing.
     */
    public function release(Reservation $reservation): void;

    /**
     * The member's points held and not yet confirmed or released, in the currency.
     */
    public function held(ResourceReference $member, string $currency): int;

    /**
     * The member's balance in the currency less the points held: what a new reservation may spend.
     */
    public function available(ResourceReference $member, string $currency): int;
}
