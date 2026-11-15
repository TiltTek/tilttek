<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use Closure;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\Bounties\Contract\BountyEscrow;
use Tilttek\Bounties\Contract\BountyStore;
use Tilttek\Bounties\Event\AwardGiven;
use Tilttek\Bounties\Event\BountyAwarded;
use Tilttek\Bounties\Event\BountyClaimed;
use Tilttek\Bounties\Event\BountyClosed;
use Tilttek\Bounties\Event\BountyOffered;
use Tilttek\Bounties\Exception\BountyConflict;
use Tilttek\Bounties\Exception\BountyRefused;
use Tilttek\Bounties\Exception\EscrowConflict;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * Playful bounties, paid in advance in points and open for a bounded time: offered with their points held, claimed,
 * awarded — to one claim or shared between several —, and settled: the awards paid, the rest given back to the
 * sponsor once the grace is over. Never money.
 *
 * Every move is checked by `Bounty`, kept at its next version only if no other writer kept one first — the service
 * reads the bounty again and tries three times —, then settled in the escrow, then announced by an event. A move made
 * again changes nothing and announces nothing. Should the escrow fail after a bounty is kept settled, `settle()` makes
 * the same escrow move again, which has the effect of one.
 *
 * A member reference a caller gives — sponsor, claimant, member to erase — is never of the kind `erased`: only
 * `erase()` draws pseudonyms, and one given back is refused with `InvalidBounty` before anything is read, written or
 * moved in the escrow.
 *
 * The service keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class Bounties
{
    public const int ATTEMPTS = 3;

    public const int MAX_BATCH = 500;

    public function __construct(
        private BountyStore $store,
        private BountyEscrow $escrow,
        private ?EventDispatcherInterface $events = null,
        private BountyRules $rules = new BountyRules(),
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * Offers the bounty the draft asks for, its points held out of the sponsor's. The same draft sent again by the same
     * sponsor gives back the bounty it offered.
     *
     * @throws InvalidBounty      when the draft is outside the site's rules, or the sponsor is of the kind `erased`
     * @throws BountyRefused      when the subject has an open bounty, the sponsor as many as the site allows, or the
     *                            identifier names another bounty
     * @throws InsufficientPoints when the sponsor's points do not cover the bounty; nothing is offered
     * @throws BountyConflict     when another writer offered a bounty of the same identifier or subject first
     */
    public function offer(BountyDraft $draft, ResourceReference $sponsor): Bounty
    {
        Check::notErased($sponsor, 'The sponsor of a bounty');

        $existing = $this->store->get($draft->id);

        if ($existing instanceof Bounty) {
            return $existing->matches($draft, $sponsor) ? $existing : throw BountyRefused::idReused($draft->id);
        }

        $open = $this->store->openOn($draft->subject);

        if (null !== $open) {
            throw BountyRefused::subjectTaken($draft->subject, $open);
        }

        if ($this->store->openBy($sponsor) >= $this->rules->openPerSponsor) {
            throw BountyRefused::tooManyOpen($sponsor, $this->rules->openPerSponsor);
        }

        $bounty = Bounty::offer($draft, $sponsor, $this->clock->now(), $this->rules);
        $this->escrow->reserve(BountyHold::of($bounty));

        try {
            $this->store->add($bounty);
        } catch (BountyConflict $bountyConflict) {
            $kept = $this->store->get($draft->id);

            if ($kept instanceof Bounty && $kept->matches($draft, $sponsor)) {
                return $kept;
            }

            if (!$kept instanceof Bounty) {
                // Another bounty took the subject first: no bounty of this identifier holds the points, which go back.
                $this->escrow->cancel(BountyHold::of($bounty));
            }

            throw $bountyConflict;
        }

        $this->dispatch(new BountyOffered($bounty->id, $sponsor, $bounty->subject));

        return $bounty;
    }

    /**
     * @throws BountyRefused when no bounty has the identifier
     */
    public function get(string $id): Bounty
    {
        return $this->store->get($id) ?? throw BountyRefused::unknown($id);
    }

    /**
     * A member claims the bounty; a claim identifier, an evidence or a claimant the library does not take is refused
     * with `InvalidBounty`.
     *
     * @throws InvalidBounty
     * @throws BountyRefused
     * @throws BountyConflict
     */
    public function claim(string $id, string $claim, ResourceReference $claimant, string $evidence): Bounty
    {
        Check::notErased($claimant, 'A claimant');

        return $this->change($id, fn (Bounty $bounty): Bounty => $bounty->claimWith($claim, $claimant, $evidence, $this->clock->now()));
    }

    /**
     * The sponsor awards points to a claim; once the whole amount is awarded, the bounty is settled. An award identifier,
     * an amount or a sponsor the library does not take is refused with `InvalidBounty`.
     *
     * @throws InvalidBounty
     * @throws BountyRefused
     * @throws BountyConflict
     */
    public function award(string $id, string $award, string $claim, int $amount, ResourceReference $sponsor): Bounty
    {
        Check::notErased($sponsor, 'The sponsor of a bounty');

        return $this->change($id, fn (Bounty $bounty): Bounty => $bounty->awardTo($award, $claim, $amount, $sponsor, $this->clock->now()));
    }

    /**
     * The sponsor closes the bounty with what it awarded; the rest goes back to the sponsor.
     *
     * @throws InvalidBounty  when the sponsor is of the kind `erased`
     * @throws BountyRefused
     * @throws BountyConflict
     */
    public function close(string $id, ResourceReference $sponsor): Bounty
    {
        Check::notErased($sponsor, 'The sponsor of a bounty');

        return $this->change($id, fn (Bounty $bounty): Bounty => $bounty->close($sponsor, $this->clock->now()));
    }

    /**
     * The sponsor withdraws the bounty before anyone claims it; its points go back to the sponsor.
     *
     * @throws InvalidBounty  when the sponsor is of the kind `erased`
     * @throws BountyRefused
     * @throws BountyConflict
     */
    public function cancel(string $id, ResourceReference $sponsor): Bounty
    {
        Check::notErased($sponsor, 'The sponsor of a bounty');

        return $this->change($id, fn (Bounty $bounty): Bounty => $bounty->cancel($sponsor, $this->clock->now()));
    }

    /**
     * Settles the bounty once its grace is over: its awards paid, the rest given back.
     *
     * @throws BountyRefused
     * @throws BountyConflict
     */
    public function expire(string $id): Bounty
    {
        return $this->change($id, fn (Bounty $bounty): Bounty => $bounty->expire($this->clock->now()));
    }

    /**
     * Settles the open bounties whose grace is over, `$limit` at most, and returns how many: run it from a scheduled
     * task. A bounty another writer changed in the meantime is left for the next run.
     */
    public function expireDue(int $limit = 100): int
    {
        $expired = 0;

        foreach ($this->store->due($this->clock->now(), max(1, min($limit, self::MAX_BATCH))) as $id) {
            try {
                $this->expire($id);
                ++$expired;
            } catch (BountyRefused|BountyConflict) {
                continue;
            }
        }

        return $expired;
    }

    /**
     * Makes the escrow move of a settled bounty again — its awards paid and the rest given back, or everything given
     * back —, which has the effect of one: after an escrow that failed once the bounty was kept. An open bounty has
     * nothing to settle, nor has one whose sponsor or a winner was erased.
     *
     * @throws BountyRefused  when no bounty has the identifier
     * @throws EscrowConflict
     */
    public function settle(string $id): void
    {
        $this->settleEscrow($this->get($id));
    }

    /**
     * Erases the member from the bounties that name them, `$limit` bounties at most, and returns how many: call it again
     * until it returns 0. In each bounty, the member first leaves — see `Bounty::leave()`: the open bounty they sponsor
     * is settled, their claims on an open bounty withdrawn with the awards given to them —, that move settled and
     * announced as any other; then a pseudonym drawn for that bounty replaces them, silently. Erase the member's
     * bounties before their points, so that points given back reach their account before it goes.
     *
     * @throws InvalidBounty  when the member is of the kind `erased`: a pseudonym is erased already
     * @throws BountyConflict when other writers keep changing a bounty; what was erased stays erased
     * @throws EscrowConflict
     */
    public function erase(ResourceReference $member, int $limit = 100): int
    {
        Check::notErased($member, 'The member to erase');
        $erased = 0;

        foreach ($this->store->involving($member, max(1, min($limit, self::MAX_BATCH))) as $id) {
            $this->change($id, fn (Bounty $bounty): Bounty => $bounty->leave($member, $this->clock->now()));
            // A bounty settled earlier, whose escrow failed then, is settled before its parties are replaced.
            $this->settleEscrow($this->get($id));
            $this->change($id, static fn (Bounty $bounty): Bounty => $bounty->pseudonymized($member, Pseudonym::draw()), false);
            ++$erased;
        }

        return $erased;
    }

    /**
     * @param Closure(Bounty): Bounty $move
     * @param bool                    $outcome false for a move that changes no outcome: neither settled nor announced
     *
     * @throws BountyRefused
     * @throws BountyConflict
     */
    private function change(string $id, Closure $move, bool $outcome = true): Bounty
    {
        for ($attempt = 1; $attempt <= self::ATTEMPTS; ++$attempt) {
            $before = $this->get($id);
            $after = $move($before);

            if ($after === $before) {
                return $before;
            }

            try {
                $this->store->update($after, $before->version);
            } catch (BountyConflict) {
                continue;
            }

            if (!$outcome) {
                return $after;
            }

            $this->settleEscrow($after);

            foreach ($this->eventsOf($before, $after) as $event) {
                $this->dispatch($event);
            }

            return $after;
        }

        throw BountyConflict::busy($id, self::ATTEMPTS);
    }

    /**
     * Pays the awards of an awarded bounty and gives the rest back, or gives everything back for a bounty otherwise
     * settled; an open bounty has nothing to settle, and a settled one never changes again. A bounty whose sponsor or
     * winner was erased was settled before they were, and is not settled again.
     */
    private function settleEscrow(Bounty $bounty): void
    {
        if (Pseudonym::isErased($bounty->sponsor) || array_any($bounty->payouts(), static fn (Payout $payout): bool => Pseudonym::isErased($payout->member))) {
            return;
        }

        if (BountyState::Awarded === $bounty->state) {
            $this->escrow->confirm(BountyHold::of($bounty), $bounty->payouts());
        } elseif ($bounty->state->isSettled()) {
            $this->escrow->cancel(BountyHold::of($bounty));
        }
    }

    /**
     * What changed from one version to the next, as events.
     *
     * @return list<object>
     */
    private function eventsOf(Bounty $before, Bounty $after): array
    {
        $events = [];

        foreach ($after->claims as $claim) {
            if (!$before->claim($claim->id) instanceof Claim) {
                $events[] = new BountyClaimed($after->id, $claim->id, $claim->claimant);
            }
        }

        foreach ($after->awards as $award) {
            if (!$before->award($award->id) instanceof Award) {
                $events[] = new AwardGiven($after->id, $award->id, $award->claim, $award->amount);
            }
        }

        if ($after->state->isSettled()) {
            foreach ($after->payouts() as $payout) {
                $events[] = new BountyAwarded($after->id, $payout->member, $payout->amount, $after->prize->unit);
            }

            $events[] = new BountyClosed($after->id, $after->state, $after->prize->amount - $after->awarded());
        }

        return $events;
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
