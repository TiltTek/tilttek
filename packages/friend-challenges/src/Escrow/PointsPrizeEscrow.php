<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Escrow;

use Override;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Exception\EscrowConflict;
use Tilttek\FriendChallenges\Payout;
use Tilttek\FriendChallenges\PrizeHold;
use Tilttek\Points\Exception\UnknownMovement;
use Tilttek\Points\Points;

/**
 * The prizes held in the ledger of `tilttek/points`, by the movements it has: a hold is a spending of the creator's
 * points under the hold's key, on the redemption account `friend-challenges`; a confirmation grants each payout from
 * the issuance account `friend-challenges`, and grants back what the payouts leave — after the spending, written again
 * under its key, which holds a hold never held —; a cancellation refunds the spending. Every movement has a key of its own, derived from the hold's, so that the same move made twice writes
 * once.
 *
 * The points leave the creator's balance when they are held. The library settles a hold once, as its state machine
 * makes sure: this escrow does not read the ledger to refuse a hold settled twice.
 */
final readonly class PointsPrizeEscrow implements PrizeEscrow
{
    public const string ACCOUNT = 'friend-challenges';

    public function __construct(
        private Points $points,
    ) {
    }

    #[Override]
    public function reserve(PrizeHold $hold): bool
    {
        return $this->points->spend($hold->payer, $hold->amount, $hold->key, self::ACCOUNT, $hold->unit)->new;
    }

    #[Override]
    public function confirm(PrizeHold $hold, array $payouts): void
    {
        $paid = array_sum(array_map(static fn (Payout $payout): int => $payout->amount, $payouts));

        if ($paid > $hold->amount) {
            throw EscrowConflict::because(\sprintf('The payouts of the hold "%s" are %d points, over the %d held.', $hold->key, $paid, $hold->amount));
        }

        $this->reserve($hold);

        foreach ($payouts as $payout) {
            $this->points->grant($payout->member, $payout->amount, $hold->key.':payout:'.hash('xxh128', $payout->member->toString()), self::ACCOUNT, $hold->unit);
        }

        if ($paid < $hold->amount) {
            $this->points->grant($hold->payer, $hold->amount - $paid, $hold->key.':remainder', self::ACCOUNT, $hold->unit);
        }
    }

    #[Override]
    public function cancel(PrizeHold $hold): void
    {
        try {
            $this->points->refund($hold->key);
        } catch (UnknownMovement) {
            // A hold never held has nothing to give back.
        }
    }
}
