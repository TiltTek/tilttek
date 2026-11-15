<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Exception\ChallengeRefused;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * A challenge a member set: its terms, who may take part and who did, the proofs and their review, its prize and where
 * it stands. It is an explicit state machine — see `ChallengeState` —: each move checks the member, the state and the
 * time, and returns the challenge at its next version, or the same object when the move was made already; a move the
 * rules refuse throws `ChallengeRefused` and changes nothing.
 *
 * Proofs are submitted until `endsAt`, and reviewed until `reviewEndsAt`; the first proof accepted wins. Every time is
 * in UTC, and times are compared by their order, never by `==` or `===`.
 */
final readonly class Challenge
{
    public const int MAX_INVITEES = 50;

    public const int MAX_PARTICIPANTS = 100;

    public const int MAX_PROOFS = 200;

    public const int MAX_PROOFS_PER_PARTICIPANT = 5;

    private const array FIELDS = ['id', 'creator', 'title', 'description', 'visibility', 'validation', 'votes_needed', 'prize', 'unit', 'created_at', 'ends_at', 'review_ends_at', 'invitees', 'declined', 'participants', 'proofs', 'state', 'winner', 'winning_proof', 'settled_at', 'version'];

    /**
     * @param list<ResourceReference> $invitees
     * @param list<ResourceReference> $declined
     * @param list<ResourceReference> $participants
     * @param list<Proof>             $proofs
     */
    private function __construct(
        public string $id,
        public ResourceReference $creator,
        public string $title,
        public string $description,
        public Visibility $visibility,
        public Validation $validation,
        public int $votesNeeded,
        public Prize $prize,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $endsAt,
        public DateTimeImmutable $reviewEndsAt,
        public array $invitees,
        public array $declined,
        public array $participants,
        public array $proofs,
        public ChallengeState $state,
        public ?ResourceReference $winner,
        public ?string $winningProof,
        public ?DateTimeImmutable $settledAt,
        public int $version,
    ) {
    }

    /**
     * The challenge a draft asks for, opened at the moment by its creator, within the site's rules.
     *
     * @throws InvalidChallenge when the draft is outside the rules: its length, its prize, or its creator invited
     */
    public static function create(ChallengeDraft $draft, ResourceReference $creator, DateTimeImmutable $at, ChallengeRules $rules): self
    {
        $at = Instant::utc($at);
        $length = $draft->endsAt->getTimestamp() - $at->getTimestamp();

        if ($length < $rules->shortest || $length > $rules->longest) {
            throw InvalidChallenge::because(\sprintf('A challenge lasts %d to %d seconds; this one lasts %d.', $rules->shortest, $rules->longest, $length));
        }

        if ($draft->prize->amount > $rules->largestPrize) {
            throw InvalidChallenge::because(\sprintf('A prize is %d points at most; %d is too many.', $rules->largestPrize, $draft->prize->amount));
        }

        if (self::among($creator, $draft->invitees)) {
            throw InvalidChallenge::because('The creator of a challenge does not invite themselves.');
        }

        return new self(
            $draft->id,
            $creator,
            $draft->title,
            $draft->description,
            $draft->visibility,
            $draft->validation,
            $draft->votesNeeded,
            $draft->prize,
            $at,
            $draft->endsAt,
            $draft->endsAt->modify(\sprintf('+%d seconds', $rules->review)),
            $draft->invitees,
            [],
            [],
            [],
            ChallengeState::Open,
            null,
            null,
            null,
            1,
        );
    }

    /**
     * Whether the challenge is the one the draft asks for, by the same creator: a creation sent again.
     */
    public function matches(ChallengeDraft $draft, ResourceReference $creator): bool
    {
        return $this->id === $draft->id
            && $this->creator->equals($creator)
            && $this->title === $draft->title
            && $this->description === $draft->description
            && $this->visibility === $draft->visibility
            && $this->validation === $draft->validation
            && $this->votesNeeded === $draft->votesNeeded
            && $this->prize->equals($draft->prize)
            && Instant::write($this->endsAt) === Instant::write($draft->endsAt)
            && $this->references($this->invitees) === $this->references($draft->invitees);
    }

    public function proof(string $id): ?Proof
    {
        foreach ($this->proofs as $proof) {
            if ($proof->id === $id) {
                return $proof;
            }
        }

        return null;
    }

    public function isParticipant(ResourceReference $member): bool
    {
        return self::among($member, $this->participants);
    }

    public function isInvited(ResourceReference $member): bool
    {
        return self::among($member, $this->invitees);
    }

    /**
     * The member takes part, invited to a challenge between friends or as anyone in an open one. A member who declined
     * may still change their mind; taking part twice changes nothing.
     *
     * @throws ChallengeRefused
     */
    public function join(ResourceReference $member, DateTimeImmutable $at): self
    {
        if ($this->creator->equals($member)) {
            throw ChallengeRefused::creatorTakesPart($this->id);
        }

        if ($this->isParticipant($member)) {
            return $this;
        }

        $this->open('take part');
        $this->before($this->endsAt, $at, 'take part');

        if (Visibility::Friends === $this->visibility && !$this->isInvited($member)) {
            throw ChallengeRefused::notInvited($this->id, $member);
        }

        if (\count($this->participants) >= self::MAX_PARTICIPANTS) {
            throw ChallengeRefused::full($this->id, 'participants', self::MAX_PARTICIPANTS);
        }

        return clone ($this, [
            'participants' => [...$this->participants, $member],
            'declined' => $this->without($member, $this->declined),
            'version' => $this->version + 1,
        ]);
    }

    /**
     * The invited member declines. The creator is not told: the challenge shows who takes part, never who declined.
     *
     * @throws ChallengeRefused
     */
    public function decline(ResourceReference $member, DateTimeImmutable $at): self
    {
        if (!$this->isInvited($member)) {
            throw ChallengeRefused::notInvited($this->id, $member);
        }

        if (self::among($member, $this->declined)) {
            return $this;
        }

        $this->open('decline');
        $this->before($this->endsAt, $at, 'decline');

        if ($this->isParticipant($member)) {
            throw ChallengeRefused::inState($this->id, $this->state, 'a participant withdraws by a moderator only');
        }

        return clone ($this, ['declined' => [...$this->declined, $member], 'version' => $this->version + 1]);
    }

    /**
     * A participant submits a proof, until the challenge ends. The same proof sent again changes nothing.
     *
     * @throws InvalidChallenge when the proof's identifier or evidence is not one the library takes
     * @throws ChallengeRefused
     */
    public function submit(string $proofId, ResourceReference $participant, string $evidence, DateTimeImmutable $at): self
    {
        $existing = $this->proof($proofId);

        if ($existing instanceof Proof) {
            if ($existing->sameSubmission($participant, $evidence)) {
                return $this;
            }

            throw ChallengeRefused::proofReused($this->id, $proofId);
        }

        $this->open('submit a proof');
        $this->before($this->endsAt, $at, 'submit a proof');

        if (!$this->isParticipant($participant)) {
            throw ChallengeRefused::notParticipant($this->id, $participant);
        }

        $own = \count(array_filter($this->proofs, static fn (Proof $proof): bool => $proof->participant->equals($participant)));

        if ($own >= self::MAX_PROOFS_PER_PARTICIPANT) {
            throw ChallengeRefused::full($this->id, 'proofs of '.$participant->toString(), self::MAX_PROOFS_PER_PARTICIPANT);
        }

        if (\count($this->proofs) >= self::MAX_PROOFS) {
            throw ChallengeRefused::full($this->id, 'proofs', self::MAX_PROOFS);
        }

        return clone ($this, [
            'proofs' => [...$this->proofs, new Proof($proofId, $participant, $evidence, $at)],
            'version' => $this->version + 1,
        ]);
    }

    /**
     * The proof is approved by its judge: the creator, a moderator, or a voter when the proofs are put to a vote. Once
     * accepted — at once by its judge, or when the votes needed approve it —, its participant wins.
     *
     * @param bool $moderates whether the actor is a moderator of the site
     *
     * @throws ChallengeRefused
     */
    public function approve(string $proofId, ResourceReference $actor, bool $moderates, DateTimeImmutable $at): self
    {
        return $this->judge($proofId, $actor, $moderates, $at, true);
    }

    /**
     * The proof is rejected by its judge, or by the votes needed when the proofs are put to a vote.
     *
     * @param bool $moderates whether the actor is a moderator of the site
     *
     * @throws ChallengeRefused
     */
    public function reject(string $proofId, ResourceReference $actor, bool $moderates, DateTimeImmutable $at): self
    {
        return $this->judge($proofId, $actor, $moderates, $at, false);
    }

    /**
     * The creator withdraws the challenge before anyone submitted a proof; its prize goes back to them.
     *
     * @throws ChallengeRefused
     */
    public function cancel(ResourceReference $actor, DateTimeImmutable $at): self
    {
        if (!$this->creator->equals($actor)) {
            throw ChallengeRefused::notCreator($this->id, $actor);
        }

        if (ChallengeState::Cancelled === $this->state) {
            return $this;
        }

        $this->open('it cannot be cancelled');

        if ([] !== $this->proofs) {
            throw ChallengeRefused::proofsSubmitted($this->id);
        }

        return $this->settled(ChallengeState::Cancelled, $at);
    }

    /**
     * The challenge ends without a winner once its review is over; its prize goes back to its creator.
     *
     * @throws ChallengeRefused
     */
    public function expire(DateTimeImmutable $at): self
    {
        if (ChallengeState::Expired === $this->state) {
            return $this;
        }

        $this->open('it cannot expire');

        if ($at < $this->reviewEndsAt) {
            throw ChallengeRefused::notDue($this->id);
        }

        return $this->settled(ChallengeState::Expired, $at);
    }

    /**
     * Whether the challenge is open and its review over at the moment.
     */
    public function isDue(DateTimeImmutable $at): bool
    {
        return ChallengeState::Open === $this->state && $at >= $this->reviewEndsAt;
    }

    /**
     * A moderator freezes the open challenge: its prize stays held, and nothing moves until it is unfrozen or removed.
     *
     * @throws ChallengeRefused
     */
    public function freeze(): self
    {
        if (ChallengeState::Frozen === $this->state) {
            return $this;
        }

        $this->open('it cannot be frozen');

        return clone ($this, ['state' => ChallengeState::Frozen, 'version' => $this->version + 1]);
    }

    /**
     * A moderator unfreezes the challenge, open again; one whose review is over expires at its next check.
     *
     * @throws ChallengeRefused
     */
    public function unfreeze(): self
    {
        if (ChallengeState::Open === $this->state) {
            return $this;
        }

        if (ChallengeState::Frozen !== $this->state) {
            throw ChallengeRefused::inState($this->id, $this->state, 'it cannot be unfrozen');
        }

        return clone ($this, ['state' => ChallengeState::Open, 'version' => $this->version + 1]);
    }

    /**
     * A moderator removes the challenge, open or frozen; its prize goes back to its creator.
     *
     * @throws ChallengeRefused
     */
    public function remove(DateTimeImmutable $at): self
    {
        if (ChallengeState::Removed === $this->state) {
            return $this;
        }

        if ($this->state->isSettled()) {
            throw ChallengeRefused::inState($this->id, $this->state, 'it cannot be removed');
        }

        return $this->settled(ChallengeState::Removed, $at);
    }

    /**
     * A moderator removes a proof waiting for its review, of an open or frozen challenge.
     *
     * @throws ChallengeRefused
     */
    public function removeProof(string $proofId): self
    {
        $proof = $this->proof($proofId) ?? throw ChallengeRefused::unknownProof($this->id, $proofId);

        if (ProofState::Removed === $proof->state) {
            return $this;
        }

        if ($this->state->isSettled()) {
            throw ChallengeRefused::inState($this->id, $this->state, 'its proofs stay as they are');
        }

        if (ProofState::Pending !== $proof->state) {
            throw ChallengeRefused::proofDecided($this->id, $proofId);
        }

        return clone ($this, ['proofs' => $this->replaced($proof->decided(ProofState::Removed)), 'version' => $this->version + 1]);
    }

    /**
     * Whether the challenge names the member: as its creator, an invitee or a participant — and so as the author of a
     * proof, a voter or the winner, who are participants or the creator.
     */
    public function involves(ResourceReference $member): bool
    {
        return $this->creator->equals($member) || $this->isInvited($member) || $this->isParticipant($member);
    }

    /**
     * The member leaves the challenge, before their erasure: the open or frozen challenge they created is removed, its
     * prize given back to them; their proofs waiting for their review in an open or frozen challenge are removed. A
     * settled challenge, or one where nothing of theirs waits, stays as it is.
     */
    public function leave(ResourceReference $member, DateTimeImmutable $at): self
    {
        if ($this->state->isSettled()) {
            return $this;
        }

        if ($this->creator->equals($member)) {
            return $this->settled(ChallengeState::Removed, $at);
        }

        $proofs = [];
        $withdrawn = false;

        foreach ($this->proofs as $proof) {
            if (ProofState::Pending === $proof->state && $proof->participant->equals($member)) {
                $proof = $proof->decided(ProofState::Removed);
                $withdrawn = true;
            }

            $proofs[] = $proof;
        }

        return $withdrawn ? clone ($this, ['proofs' => $proofs, 'version' => $this->version + 1]) : $this;
    }

    /**
     * The challenge with the member replaced by the pseudonym wherever it names them — creator, invitee, participant,
     * author of proofs, voter, winner —, and the evidence of their proofs replaced by the pseudonym too. The title and
     * the terms stay: a site that wants them gone removes the challenge first.
     */
    public function pseudonymized(ResourceReference $member, ResourceReference $pseudonym): self
    {
        if (!$this->involves($member)) {
            return $this;
        }

        $swap = static fn (ResourceReference $candidate): ResourceReference => $candidate->equals($member) ? $pseudonym : $candidate;
        $vote = static fn (string $voter): string => $voter === $member->toString() ? $pseudonym->toString() : $voter;
        $proofs = array_map(static function (Proof $proof) use ($member, $pseudonym, $vote): Proof {
            $voted = $proof->voted(array_map($vote, $proof->approvals), array_map($vote, $proof->rejections));

            return $proof->participant->equals($member) ? $voted->writtenBy($pseudonym) : $voted;
        }, $this->proofs);

        return clone ($this, [
            'creator' => $swap($this->creator),
            'invitees' => array_map($swap, $this->invitees),
            'declined' => array_map($swap, $this->declined),
            'participants' => array_map($swap, $this->participants),
            'proofs' => $proofs,
            'winner' => $this->winner instanceof ResourceReference ? $swap($this->winner) : null,
            'version' => $this->version + 1,
        ]);
    }

    /**
     * The challenge as a record to keep, which `fromArray()` reads back.
     *
     * @return array{id: string, creator: string, title: string, description: string, visibility: string, validation: string, votes_needed: int, prize: int, unit: string, created_at: string, ends_at: string, review_ends_at: string, invitees: list<string>, declined: list<string>, participants: list<string>, proofs: list<array{id: string, participant: string, evidence: string, submitted_at: string, state: string, approvals: list<string>, rejections: list<string>}>, state: string, winner: ?string, winning_proof: ?string, settled_at: ?string, version: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'creator' => $this->creator->toString(),
            'title' => $this->title,
            'description' => $this->description,
            'visibility' => $this->visibility->value,
            'validation' => $this->validation->value,
            'votes_needed' => $this->votesNeeded,
            'prize' => $this->prize->amount,
            'unit' => $this->prize->unit,
            'created_at' => Instant::write($this->createdAt),
            'ends_at' => Instant::write($this->endsAt),
            'review_ends_at' => Instant::write($this->reviewEndsAt),
            'invitees' => $this->references($this->invitees),
            'declined' => $this->references($this->declined),
            'participants' => $this->references($this->participants),
            'proofs' => array_map(static fn (Proof $proof): array => $proof->toArray(), $this->proofs),
            'state' => $this->state->value,
            'winner' => $this->winner?->toString(),
            'winning_proof' => $this->winningProof,
            'settled_at' => $this->settledAt instanceof DateTimeImmutable ? Instant::write($this->settledAt) : null,
            'version' => $this->version,
        ];
    }

    /**
     * Reads back a record `toArray()` wrote, checked field by field and as a whole.
     *
     * @param array<array-key, mixed> $record
     *
     * @throws InvalidChallenge when the record is not one `toArray()` writes
     */
    public static function fromArray(array $record): self
    {
        $read = new Reader($record, 'a challenge', self::FIELDS);
        $votesNeeded = $read->int('votes_needed');
        $visibility = $read->enum('visibility', Visibility::class);
        $validation = $read->enum('validation', Validation::class);
        $endsAt = $read->time('ends_at');
        $invitees = $read->members('invitees', self::MAX_INVITEES);
        $draft = new ChallengeDraft($read->string('id'), $read->string('title'), new Prize($read->int('prize'), $read->string('unit')), $endsAt, $invitees, $visibility, $validation, $read->string('description'), $votesNeeded);

        if ($draft->title !== $read->string('title') || $draft->description !== $read->string('description')) {
            throw InvalidChallenge::because('The title and the terms of a challenge are kept trimmed.');
        }

        $proofs = [];

        foreach ($read->list('proofs', self::MAX_PROOFS) as $proof) {
            if (!\is_array($proof)) {
                throw InvalidChallenge::because('The proofs of a challenge are records.');
            }

            $proofs[] = ChallengeRecord::proof($proof);
        }

        $challenge = new self(
            $draft->id,
            $read->member('creator'),
            $draft->title,
            $draft->description,
            $visibility,
            $validation,
            $votesNeeded,
            $draft->prize,
            $read->time('created_at'),
            $endsAt,
            $read->time('review_ends_at'),
            $invitees,
            $read->members('declined', self::MAX_INVITEES),
            $read->members('participants', self::MAX_PARTICIPANTS),
            $proofs,
            $read->enum('state', ChallengeState::class),
            $read->nullableMember('winner'),
            $read->nullableString('winning_proof'),
            $read->nullableTime('settled_at'),
            $read->int('version'),
        );
        ChallengeRecord::verify($challenge);

        return $challenge;
    }

    /**
     * @throws ChallengeRefused
     */
    private function judge(string $proofId, ResourceReference $actor, bool $moderates, DateTimeImmutable $at, bool $approves): self
    {
        $proof = $this->proof($proofId) ?? throw ChallengeRefused::unknownProof($this->id, $proofId);

        if (Validation::Vote === $this->validation) {
            return $this->vote($proof, $actor, $at, $approves);
        }

        if (Validation::Creator === $this->validation ? !$this->creator->equals($actor) : !$moderates) {
            throw ChallengeRefused::notJudge($this->id, $actor);
        }

        $wanted = $approves ? ProofState::Accepted : ProofState::Rejected;

        if ($wanted === $proof->state) {
            return $this;
        }

        $this->reviewable($proof, $at);

        return $this->decided($proof->decided($wanted), $at);
    }

    /**
     * @throws ChallengeRefused
     */
    private function vote(Proof $proof, ResourceReference $actor, DateTimeImmutable $at, bool $approves): self
    {
        if (!$this->mayVote($actor, $proof)) {
            throw ChallengeRefused::notVoter($this->id, $actor);
        }

        $voter = $actor->toString();

        if (\in_array($voter, $approves ? $proof->approvals : $proof->rejections, true)) {
            return $this;
        }

        if (\in_array($voter, $approves ? $proof->rejections : $proof->approvals, true)) {
            throw ChallengeRefused::voteChanged($this->id, $proof->id, $actor);
        }

        $this->reviewable($proof, $at);
        $proof = $approves ? $proof->voted([...$proof->approvals, $voter], $proof->rejections) : $proof->voted($proof->approvals, [...$proof->rejections, $voter]);

        if (\count($approves ? $proof->approvals : $proof->rejections) < $this->votesNeeded) {
            return clone ($this, ['proofs' => $this->replaced($proof), 'version' => $this->version + 1]);
        }

        return $this->decided($proof->decided($approves ? ProofState::Accepted : ProofState::Rejected), $at);
    }

    /**
     * @throws ChallengeRefused
     */
    private function reviewable(Proof $proof, DateTimeImmutable $at): void
    {
        $this->open('its proofs are not reviewed');
        $this->before($this->reviewEndsAt, $at, 'review a proof');

        if (ProofState::Pending !== $proof->state) {
            throw ChallengeRefused::proofDecided($this->id, $proof->id);
        }
    }

    /**
     * The challenge with the proof decided: won by its participant when it is accepted.
     */
    private function decided(Proof $proof, DateTimeImmutable $at): self
    {
        if (ProofState::Accepted !== $proof->state) {
            return clone ($this, ['proofs' => $this->replaced($proof), 'version' => $this->version + 1]);
        }

        return clone ($this, [
            'proofs' => $this->replaced($proof),
            'state' => ChallengeState::Won,
            'winner' => $proof->participant,
            'winningProof' => $proof->id,
            'settledAt' => Instant::utc($at),
            'version' => $this->version + 1,
        ]);
    }

    /**
     * Whether the member votes on the proof when the proofs are put to a vote: the creator, or another participant.
     */
    public function mayVote(ResourceReference $member, Proof $proof): bool
    {
        return !$proof->participant->equals($member) && ($this->creator->equals($member) || $this->isParticipant($member));
    }

    /**
     * @throws ChallengeRefused
     */
    private function open(string $what): void
    {
        if (ChallengeState::Open !== $this->state) {
            throw ChallengeRefused::inState($this->id, $this->state, $what);
        }
    }

    /**
     * @throws ChallengeRefused
     */
    private function before(DateTimeImmutable $limit, DateTimeImmutable $at, string $what): void
    {
        if ($at >= $limit) {
            throw ChallengeRefused::closed($this->id, $what);
        }
    }

    private function settled(ChallengeState $state, DateTimeImmutable $at): self
    {
        return clone ($this, ['state' => $state, 'settledAt' => Instant::utc($at), 'version' => $this->version + 1]);
    }

    /**
     * @return list<Proof>
     */
    private function replaced(Proof $replacement): array
    {
        return array_map(static fn (Proof $proof): Proof => $proof->id === $replacement->id ? $replacement : $proof, $this->proofs);
    }

    /**
     * @param list<ResourceReference> $members
     */
    private static function among(ResourceReference $member, array $members): bool
    {
        return array_any($members, static fn (ResourceReference $candidate): bool => $candidate->equals($member));
    }

    /**
     * @param list<ResourceReference> $members
     *
     * @return list<ResourceReference>
     */
    private function without(ResourceReference $member, array $members): array
    {
        return array_values(array_filter($members, static fn (ResourceReference $candidate): bool => !$candidate->equals($member)));
    }

    /**
     * @param list<ResourceReference> $members
     *
     * @return list<string>
     */
    private function references(array $members): array
    {
        return array_map(static fn (ResourceReference $member): string => $member->toString(), $members);
    }
}
