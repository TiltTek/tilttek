<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Contract;

use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\Exception\EscrowConflict;
use Tilttek\Bounties\Payout;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * The points held for the bounties, paid in advance, in three moves: reserved out of the sponsor's points when a bounty
 * is offered, confirmed — paid to the winners, the rest given back — when it is awarded, cancelled — all given back —
 * when it is cancelled or expires without an award. Each move is keyed by the hold, so that the same move made twice has the effect of one; the
 * library settles a hold once, confirmed or cancelled and never both.
 *
 * The moves follow those of the `PointsHold` contract of `tilttek/exchange-bundle` — hold, confirm, release — and take
 * the whole hold, as that contract takes the whole reservation.
 */
interface BountyEscrow
{
    /**
     * Holds the amount out of the payer's points. Returns true when this call held it, false when the same hold was
     * held already.
     *
     * @throws InsufficientPoints when the payer's available points do not cover the amount; nothing is held
     * @throws EscrowConflict     when another hold is held under the same key
     */
    public function reserve(BountyHold $hold): bool;

    /**
     * Pays the held points to the members, and gives what the payouts leave back to the payer. Confirming again changes
     * nothing; confirming a hold never held holds it first, so that no point is paid that the payer did not give.
     *
     * @param list<Payout> $payouts at most the amount held, in all
     *
     * @throws InsufficientPoints when the hold was never held and the payer's points do not cover it
     * @throws EscrowConflict     when the payouts are over the amount, or the hold was cancelled or paid otherwise
     */
    public function confirm(BountyHold $hold, array $payouts): void;

    /**
     * Gives the held points back to the payer. Cancelling again, or cancelling a hold never held, changes nothing.
     *
     * @throws EscrowConflict when the hold was confirmed
     */
    public function cancel(BountyHold $hold): void;
}
