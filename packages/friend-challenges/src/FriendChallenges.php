<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use Closure;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\FriendChallenges\Contract\ChallengeStore;
use Tilttek\FriendChallenges\Contract\Friendship;
use Tilttek\FriendChallenges\Contract\Moderation;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Event\ChallengeCreated;
use Tilttek\FriendChallenges\Event\ChallengeEnded;
use Tilttek\FriendChallenges\Event\ChallengeFrozen;
use Tilttek\FriendChallenges\Event\ChallengeUnfrozen;
use Tilttek\FriendChallenges\Event\ChallengeWon;
use Tilttek\FriendChallenges\Event\MemberInvited;
use Tilttek\FriendChallenges\Event\ParticipantJoined;
use Tilttek\FriendChallenges\Event\ProofDecided;
use Tilttek\FriendChallenges\Event\ProofSubmitted;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;
use Tilttek\FriendChallenges\Exception\ChallengeRefused;
use Tilttek\FriendChallenges\Exception\EscrowConflict;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * The challenges members set each other: created with their prize held in escrow, taken part in, proved, reviewed,
 * and settled — the prize paid to the winner, or given back to the creator when the challenge is cancelled, expires
 * or is removed by a moderator.
 *
 * Every move is checked by `Challenge`, kept at its next version only if no other writer kept one first — the service
 * reads the challenge again and tries three times —, then settled in the escrow, then announced by an event. A move
 * made again changes nothing and announces nothing. Should the escrow fail after a challenge is kept settled,
 * `settle()` makes the same escrow move again, which has the effect of one.
 *
 * A member reference a caller gives — creator, invitee, participant, judge, author of a report, moderator, member to
 * erase — is never of the kind `erased`: only `erase()` draws pseudonyms, and one given back is refused with
 * `InvalidChallenge` before anything is read, written or moved in the escrow.
 *
 * The service keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class FriendChallenges
{
    public const int ATTEMPTS = 3;

    public const int MAX_BATCH = 500;

    public function __construct(
        private ChallengeStore $store,
        private PrizeEscrow $escrow,
        private Friendship $friendship,
        private Moderation $moderation,
        private ?EventDispatcherInterface $events = null,
        private ChallengeRules $rules = new ChallengeRules(),
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * Creates the challenge the draft asks for, its prize held out of the creator's points. The same draft sent again
     * by the same creator gives back the challenge it created.
     *
     * @throws InvalidChallenge   when the draft is outside the site's rules, or the creator or an invitee is of the kind
     *                            `erased`
     * @throws ChallengeRefused   when an invitee is not a friend of the creator, or the identifier names another challenge
     * @throws InsufficientPoints when the creator's points do not cover the prize; nothing is created
     */
    public function create(ChallengeDraft $draft, ResourceReference $creator): Challenge
    {
        Check::notErased($creator, 'The creator of a challenge');

        foreach ($draft->invitees as $invitee) {
            Check::notErased($invitee, 'An invitee');
        }

        $existing = $this->store->get($draft->id);

        if ($existing instanceof Challenge) {
            return $existing->matches($draft, $creator) ? $existing : throw ChallengeRefused::idReused($draft->id);
        }

        $challenge = Challenge::create($draft, $creator, $this->clock->now(), $this->rules);

        foreach ($challenge->invitees as $invitee) {
            if (!$this->friendship->areFriends($creator, $invitee)) {
                throw ChallengeRefused::notFriend($creator, $invitee);
            }
        }

        $this->escrow->reserve(PrizeHold::of($challenge));

        try {
            $this->store->add($challenge);
        } catch (ChallengeConflict $challengeConflict) {
            $kept = $this->store->get($draft->id);

            if ($kept instanceof Challenge && $kept->matches($draft, $creator)) {
                return $kept;
            }

            throw $challengeConflict;
        }

        $this->dispatch(new ChallengeCreated($challenge->id, $creator));

        foreach ($challenge->invitees as $invitee) {
            $this->dispatch(new MemberInvited($challenge->id, $invitee));
        }

        return $challenge;
    }

    /**
     * @throws ChallengeRefused when no challenge has the identifier
     */
    public function get(string $id): Challenge
    {
        return $this->store->get($id) ?? throw ChallengeRefused::unknown($id);
    }

    /**
     * @throws InvalidChallenge  when the member is of the kind `erased`
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function join(string $id, ResourceReference $member): Challenge
    {
        Check::notErased($member, 'A participant');

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->join($member, $this->clock->now()));
    }

    /**
     * @throws InvalidChallenge  when the member is of the kind `erased`
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function decline(string $id, ResourceReference $member): Challenge
    {
        Check::notErased($member, 'An invitee');

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->decline($member, $this->clock->now()));
    }

    /**
     * A participant submits a proof; a proof identifier, an evidence or a participant the library does not take is
     * refused with `InvalidChallenge`.
     *
     * @throws InvalidChallenge
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function submitProof(string $id, string $proof, ResourceReference $participant, string $evidence): Challenge
    {
        Check::notErased($participant, 'A participant');

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->submit($proof, $participant, $evidence, $this->clock->now()));
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function approve(string $id, string $proof, ResourceReference $actor): Challenge
    {
        Check::notErased($actor, 'A judge');

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->approve($proof, $actor, $this->moderation->mayModerate($actor), $this->clock->now()));
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function reject(string $id, string $proof, ResourceReference $actor): Challenge
    {
        Check::notErased($actor, 'A judge');

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->reject($proof, $actor, $this->moderation->mayModerate($actor), $this->clock->now()));
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function cancel(string $id, ResourceReference $actor): Challenge
    {
        Check::notErased($actor, 'The creator of a challenge');

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->cancel($actor, $this->clock->now()));
    }

    /**
     * Ends the challenge without a winner once its review is over.
     *
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    public function expire(string $id): Challenge
    {
        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->expire($this->clock->now()));
    }

    /**
     * Expires the open challenges whose review is over, `$limit` at most, and returns how many expired: run it from a
     * scheduled task. A challenge another writer changed in the meantime is left for the next run.
     */
    public function expireDue(int $limit = 100): int
    {
        $expired = 0;
        $now = $this->clock->now();

        foreach ($this->store->due($now, max(1, min($limit, self::MAX_BATCH))) as $id) {
            try {
                $this->expire($id);
                ++$expired;
            } catch (ChallengeRefused|ChallengeConflict) {
                continue;
            }
        }

        return $expired;
    }

    /**
     * Sends a member's report of the challenge, or of one of its proofs, to the moderators of the site.
     *
     * @throws ChallengeRefused when the challenge or the proof does not exist
     * @throws InvalidChallenge when the note is not one the library takes, or the reporter is of the kind `erased`
     */
    public function report(string $id, ResourceReference $reporter, ReportReason $reason, ?string $proof = null, string $note = ''): void
    {
        Check::notErased($reporter, 'The author of a report');

        $challenge = $this->get($id);

        if (null !== $proof && !$challenge->proof($proof) instanceof Proof) {
            throw ChallengeRefused::unknownProof($id, $proof);
        }

        $this->moderation->report(new Report($id, $reporter, $reason, $this->clock->now(), $proof, $note));
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused  when the actor is not a moderator, or the challenge is not open
     * @throws ChallengeConflict
     */
    public function freeze(string $id, ResourceReference $moderator): Challenge
    {
        $this->moderator($moderator);

        return $this->change($id, static fn (Challenge $challenge): Challenge => $challenge->freeze());
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused  when the actor is not a moderator, or the challenge is not frozen
     * @throws ChallengeConflict
     */
    public function unfreeze(string $id, ResourceReference $moderator): Challenge
    {
        $this->moderator($moderator);

        return $this->change($id, static fn (Challenge $challenge): Challenge => $challenge->unfreeze());
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused  when the actor is not a moderator, or the challenge is settled
     * @throws ChallengeConflict
     */
    public function remove(string $id, ResourceReference $moderator): Challenge
    {
        $this->moderator($moderator);

        return $this->change($id, fn (Challenge $challenge): Challenge => $challenge->remove($this->clock->now()));
    }

    /**
     * @throws InvalidChallenge  when the actor is of the kind `erased`
     * @throws ChallengeRefused  when the actor is not a moderator, or the proof is decided
     * @throws ChallengeConflict
     */
    public function removeProof(string $id, string $proof, ResourceReference $moderator): Challenge
    {
        $this->moderator($moderator);

        return $this->change($id, static fn (Challenge $challenge): Challenge => $challenge->removeProof($proof));
    }

    /**
     * Makes the escrow move of a settled challenge again — the prize paid to its winner, or given back to its creator
     * —, which has the effect of one: after an escrow that failed once the challenge was kept. An open or frozen
     * challenge has nothing to settle, nor has one whose creator or winner was erased.
     *
     * @throws ChallengeRefused when no challenge has the identifier
     * @throws EscrowConflict
     */
    public function settle(string $id): void
    {
        $this->settleEscrow($this->get($id));
    }

    /**
     * Erases the member from the challenges that name them, `$limit` challenges at most, and returns how many: call it
     * again until it returns 0. In each challenge, the member first leaves — see `Challenge::leave()`: the open or
     * frozen challenge they created is removed and its prize given back to them, their proofs waiting for a review are
     * removed —, that move settled and announced as any other; then a pseudonym drawn for that challenge replaces them,
     * silently. Erase the member's challenges before their points, so that a prize given back reaches their account
     * before it goes.
     *
     * The reports the member sent and the friendships they had are the site's, erased by the site.
     *
     * @throws InvalidChallenge  when the member is of the kind `erased`: a pseudonym is erased already
     * @throws ChallengeConflict when other writers keep changing a challenge; what was erased stays erased
     * @throws EscrowConflict
     */
    public function erase(ResourceReference $member, int $limit = 100): int
    {
        Check::notErased($member, 'The member to erase');

        $erased = 0;

        foreach ($this->store->involving($member, max(1, min($limit, self::MAX_BATCH))) as $id) {
            $this->change($id, fn (Challenge $challenge): Challenge => $challenge->leave($member, $this->clock->now()));
            // A challenge settled earlier, whose escrow failed then, is settled before its parties are replaced.
            $this->settleEscrow($this->get($id));
            $this->change($id, static fn (Challenge $challenge): Challenge => $challenge->pseudonymized($member, Pseudonym::draw()), false);
            ++$erased;
        }

        return $erased;
    }

    /**
     * @param Closure(Challenge): Challenge $move
     * @param bool                          $outcome false for a move that changes no outcome: neither settled nor announced
     *
     * @throws ChallengeRefused
     * @throws ChallengeConflict
     */
    private function change(string $id, Closure $move, bool $outcome = true): Challenge
    {
        for ($attempt = 1; $attempt <= self::ATTEMPTS; ++$attempt) {
            $before = $this->get($id);
            $after = $move($before);

            if ($after === $before) {
                return $before;
            }

            try {
                $this->store->update($after, $before->version);
            } catch (ChallengeConflict) {
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

        throw ChallengeConflict::busy($id, self::ATTEMPTS);
    }

    /**
     * Pays the prize of a won challenge, or gives back that of a challenge otherwise settled; an open or frozen
     * challenge has nothing to settle, and a settled one never changes again. A challenge whose creator or winner was
     * erased was settled before they were, and is not settled again.
     */
    private function settleEscrow(Challenge $challenge): void
    {
        if (Pseudonym::isErased($challenge->creator) || ($challenge->winner instanceof ResourceReference && Pseudonym::isErased($challenge->winner))) {
            return;
        }

        if ($challenge->winner instanceof ResourceReference) {
            $this->escrow->confirm(PrizeHold::of($challenge), [new Payout($challenge->winner, $challenge->prize->amount)]);
        } elseif ($challenge->state->isSettled()) {
            $this->escrow->cancel(PrizeHold::of($challenge));
        }
    }

    /**
     * What changed from one version to the next, as events.
     *
     * @return list<object>
     */
    private function eventsOf(Challenge $before, Challenge $after): array
    {
        $events = [];

        foreach ($after->participants as $member) {
            if (!$before->isParticipant($member)) {
                $events[] = new ParticipantJoined($after->id, $member);
            }
        }

        foreach ($after->proofs as $proof) {
            $previous = $before->proof($proof->id);

            if (!$previous instanceof Proof) {
                $events[] = new ProofSubmitted($after->id, $proof->id, $proof->participant);
            } elseif ($previous->state !== $proof->state) {
                $events[] = new ProofDecided($after->id, $proof->id, $proof->state);
            }
        }

        if ($after->state === $before->state) {
            return $events;
        }

        $events[] = match ($after->state) {
            ChallengeState::Won => new ChallengeWon($after->id, $after->winner ?? $after->creator, $after->winningProof ?? '', $after->prize->amount, $after->prize->unit),
            ChallengeState::Frozen => new ChallengeFrozen($after->id),
            ChallengeState::Open => new ChallengeUnfrozen($after->id),
            default => new ChallengeEnded($after->id, $after->state),
        };

        return $events;
    }

    /**
     * @throws InvalidChallenge
     * @throws ChallengeRefused
     */
    private function moderator(ResourceReference $member): void
    {
        Check::notErased($member, 'A moderator');

        if (!$this->moderation->mayModerate($member)) {
            throw ChallengeRefused::notModerator($member);
        }
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
