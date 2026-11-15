<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Escrow;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Exception\EscrowConflict;
use Tilttek\FriendChallenges\PrizeHold;
use Tilttek\Points\Account;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * An escrow in memory, for the tests and the demonstrations: the members' points funded by hand, the holds taken out
 * of them, and each hold settled once. It keeps the contract every escrow keeps, conflicts included.
 */
final class InMemoryPrizeEscrow implements PrizeEscrow
{
    /** @var array<string, array<string, int>> the points of each member, by unit */
    private array $points = [];

    /** @var array<string, array{hold: PrizeHold, settled: ?string}> */
    private array $holds = [];

    public function fund(ResourceReference $member, int $amount, string $unit = 'points'): void
    {
        $this->points[$unit][$member->toString()] = $this->balance($member, $unit) + $amount;
    }

    /**
     * The member's points, the held ones left out.
     */
    public function balance(ResourceReference $member, string $unit = 'points'): int
    {
        return $this->points[$unit][$member->toString()] ?? 0;
    }

    /**
     * The points held under the key and not yet settled.
     */
    public function held(string $key): int
    {
        $held = $this->holds[$key] ?? null;

        return null === $held || null !== $held['settled'] ? 0 : $held['hold']->amount;
    }

    #[Override]
    public function reserve(PrizeHold $hold): bool
    {
        $held = $this->holds[$hold->key] ?? null;

        if (null !== $held) {
            if (!$held['hold']->equals($hold)) {
                throw EscrowConflict::because(\sprintf('Another hold is held under the key "%s".', $hold->key));
            }

            return false;
        }

        $balance = $this->balance($hold->payer, $hold->unit);

        if ($balance < $hold->amount) {
            throw InsufficientPoints::in(Account::member($hold->payer), $hold->unit, $balance, $hold->amount);
        }

        $this->points[$hold->unit][$hold->payer->toString()] = $balance - $hold->amount;
        $this->holds[$hold->key] = ['hold' => $hold, 'settled' => null];

        return true;
    }

    #[Override]
    public function confirm(PrizeHold $hold, array $payouts): void
    {
        $paid = 0;
        $settlement = [];

        foreach ($payouts as $payout) {
            $paid += $payout->amount;
            $settlement[] = $payout->member->toString().'='.$payout->amount;
        }

        if ($paid > $hold->amount) {
            throw EscrowConflict::because(\sprintf('The payouts of the hold "%s" are %d points, over the %d held.', $hold->key, $paid, $hold->amount));
        }

        $this->reserve($hold);

        if (!$this->settles($hold, 'paid:'.implode(',', $settlement))) {
            return;
        }

        foreach ($payouts as $payout) {
            $this->fund($payout->member, $payout->amount, $hold->unit);
        }

        if ($paid < $hold->amount) {
            $this->fund($hold->payer, $hold->amount - $paid, $hold->unit);
        }
    }

    #[Override]
    public function cancel(PrizeHold $hold): void
    {
        if ($this->settles($hold, 'cancelled')) {
            $this->fund($hold->payer, $hold->amount, $hold->unit);
        }
    }

    /**
     * Whether this call settles the hold: false when it was never held or was settled the same way already.
     *
     * @throws EscrowConflict when it was settled another way
     */
    private function settles(PrizeHold $hold, string $settlement): bool
    {
        $held = $this->holds[$hold->key] ?? null;

        if (null === $held || $held['settled'] === $settlement) {
            return false;
        }

        if (null !== $held['settled'] || !$held['hold']->equals($hold)) {
            throw EscrowConflict::because(\sprintf('The hold "%s" is settled already, another way.', $hold->key));
        }

        $this->holds[$hold->key]['settled'] = $settlement;

        return true;
    }
}
