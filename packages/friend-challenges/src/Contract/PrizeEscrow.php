<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Contract;

use Tilttek\FriendChallenges\Exception\EscrowConflict;
use Tilttek\FriendChallenges\Payout;
use Tilttek\FriendChallenges\PrizeHold;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * The points held for the prizes, in three moves: reserved out of the creator's points when a challenge is created,
 * confirmed — paid to the winner — when it is won, cancelled — given back to the creator — when it is cancelled,
 * expires or is removed. Each move is keyed by the hold, so that the same move made twice has the effect of one; the
 * library settles a hold once, confirmed or cancelled and never both.
 *
 * The moves follow those of the `PointsHold` contract of `tilttek/exchange-bundle` — hold, confirm, release — and take
 * the whole hold, as that contract takes the whole reservation.
 */
interface PrizeEscrow
{
    /**
     * Holds the amount out of the payer's points. Returns true when this call held it, false when the same hold was
     * held already.
     *
     * @throws InsufficientPoints when the payer's available points do not cover the amount; nothing is held
     * @throws EscrowConflict     when another hold is held under the same key
     */
    public function reserve(PrizeHold $hold): bool;

    /**
     * Pays the held points to the members, and gives what the payouts leave back to the payer. Confirming again changes
     * nothing; confirming a hold never held holds it first, so that no point is paid that the payer did not give.
     *
     * @param list<Payout> $payouts at most the amount held, in all
     *
     * @throws InsufficientPoints when the hold was never held and the payer's points do not cover it
     * @throws EscrowConflict     when the payouts are over the amount, or the hold was cancelled or paid otherwise
     */
    public function confirm(PrizeHold $hold, array $payouts): void;

    /**
     * Gives the held points back to the payer. Cancelling again, or cancelling a hold never held, changes nothing.
     *
     * @throws EscrowConflict when the hold was confirmed
     */
    public function cancel(PrizeHold $hold): void;
}
