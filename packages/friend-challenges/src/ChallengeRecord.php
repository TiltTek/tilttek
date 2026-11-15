<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * The checks of a challenge read back from storage, as a whole: what each move of `Challenge` keeps true, a record
 * must hold too.
 *
 * @internal
 */
final class ChallengeRecord
{
    private const array PROOF_FIELDS = ['id', 'participant', 'evidence', 'submitted_at', 'state', 'approvals', 'rejections'];

    private function __construct()
    {
    }

    /**
     * @throws InvalidChallenge
     */
    public static function verify(Challenge $challenge): void
    {
        if ($challenge->endsAt <= $challenge->createdAt || $challenge->reviewEndsAt < $challenge->endsAt) {
            throw InvalidChallenge::because('A challenge ends after it is created, and its review ends once it ends or later.');
        }

        if ($challenge->version < 1) {
            throw InvalidChallenge::because('A challenge is at version 1 or later.');
        }

        if ($challenge->isInvited($challenge->creator) || $challenge->isParticipant($challenge->creator)) {
            throw InvalidChallenge::because('The creator of a challenge does not take part in it.');
        }

        foreach ($challenge->declined as $member) {
            if (!$challenge->isInvited($member) || $challenge->isParticipant($member)) {
                throw InvalidChallenge::because('A member who declined was invited, and does not take part.');
            }
        }

        if (Visibility::Friends === $challenge->visibility) {
            foreach ($challenge->participants as $member) {
                if (!$challenge->isInvited($member)) {
                    throw InvalidChallenge::because('The participants of a challenge between friends were invited.');
                }
            }
        }

        self::verifyProofs($challenge, array_fill_keys(array_map(static fn (ResourceReference $member): string => $member->toString(), $challenge->participants), true));
        self::verifyOutcome($challenge);
    }

    /**
     * @param array<string, true> $participants
     *
     * @throws InvalidChallenge
     */
    private static function verifyProofs(Challenge $challenge, array $participants): void
    {
        $seen = [];
        $own = [];

        foreach ($challenge->proofs as $proof) {
            if (isset($seen[$proof->id])) {
                throw InvalidChallenge::because(\sprintf('The proof "%s" is listed twice.', $proof->id));
            }

            $seen[$proof->id] = true;
            $participant = $proof->participant->toString();
            $own[$participant] = ($own[$participant] ?? 0) + 1;

            if (!isset($participants[$participant]) || $own[$participant] > Challenge::MAX_PROOFS_PER_PARTICIPANT) {
                throw InvalidChallenge::because(\sprintf('The proof "%s" is one of at most %d of a participant.', $proof->id, Challenge::MAX_PROOFS_PER_PARTICIPANT));
            }

            if ($proof->submittedAt < $challenge->createdAt || $proof->submittedAt >= $challenge->endsAt) {
                throw InvalidChallenge::because(\sprintf('The proof "%s" was submitted while the challenge was open.', $proof->id));
            }

            self::verifyVotes($challenge, $proof);
        }
    }

    /**
     * @throws InvalidChallenge
     */
    private static function verifyVotes(Challenge $challenge, Proof $proof): void
    {
        $votes = [...$proof->approvals, ...$proof->rejections];

        if (Validation::Vote !== $challenge->validation && [] !== $votes) {
            throw InvalidChallenge::because(\sprintf('The proof "%s" has votes, but the proofs are not put to a vote.', $proof->id));
        }

        if (\count($votes) !== \count(array_unique($votes)) || \count($proof->approvals) > $challenge->votesNeeded || \count($proof->rejections) > $challenge->votesNeeded) {
            throw InvalidChallenge::because(\sprintf('The proof "%s" has each voter once, and no more votes than needed.', $proof->id));
        }

        foreach ($votes as $vote) {
            if (!$challenge->mayVote(Check::member($vote), $proof)) {
                throw InvalidChallenge::because(\sprintf('A vote on the proof "%s" is by the creator or another participant.', $proof->id));
            }
        }

        if (Validation::Vote === $challenge->validation
            && (ProofState::Accepted === $proof->state) !== (\count($proof->approvals) === $challenge->votesNeeded)) {
            throw InvalidChallenge::because(\sprintf('The proof "%s" is accepted when, and only when, the votes needed approve it.', $proof->id));
        }

        if (Validation::Vote === $challenge->validation
            && (ProofState::Rejected === $proof->state) !== (\count($proof->rejections) === $challenge->votesNeeded)) {
            throw InvalidChallenge::because(\sprintf('The proof "%s" is rejected when, and only when, the votes needed reject it.', $proof->id));
        }
    }

    /**
     * @throws InvalidChallenge
     */
    private static function verifyOutcome(Challenge $challenge): void
    {
        $accepted = array_values(array_filter($challenge->proofs, static fn (Proof $proof): bool => ProofState::Accepted === $proof->state));
        $winning = 1 === \count($accepted) ? $accepted[0] : null;

        if ([] !== $accepted && !$winning instanceof Proof) {
            throw InvalidChallenge::because('A challenge has one accepted proof at most.');
        }

        if ((ChallengeState::Won === $challenge->state) !== ($winning instanceof Proof)) {
            throw InvalidChallenge::because('A won challenge has one accepted proof, and any other none.');
        }

        if ($winning instanceof Proof
            ? $winning->id !== $challenge->winningProof || !$challenge->winner instanceof ResourceReference || !$winning->participant->equals($challenge->winner)
            : null !== $challenge->winningProof || $challenge->winner instanceof ResourceReference) {
            throw InvalidChallenge::because('The winner of a challenge is the participant of its accepted proof.');
        }

        if ($challenge->state->isSettled() !== ($challenge->settledAt instanceof DateTimeImmutable)) {
            throw InvalidChallenge::because('A challenge has a settlement time when, and only when, its prize is settled.');
        }

        if ($challenge->settledAt instanceof DateTimeImmutable && $challenge->settledAt < $challenge->createdAt) {
            throw InvalidChallenge::because('A challenge is settled after it is created.');
        }

        if (ChallengeState::Cancelled === $challenge->state && [] !== $challenge->proofs) {
            throw InvalidChallenge::because('A challenge with proofs is not cancelled by its creator.');
        }
    }

    /**
     * @param array<array-key, mixed> $record
     *
     * @throws InvalidChallenge
     */
    public static function proof(array $record): Proof
    {
        $read = new Reader($record, 'a proof', self::PROOF_FIELDS);
        $votes = [];

        foreach (['approvals', 'rejections'] as $field) {
            $votes[$field] = array_map(static fn (ResourceReference $member): string => $member->toString(), $read->members($field, ChallengeDraft::MAX_VOTES));
        }

        return new Proof($read->string('id'), $read->member('participant'), $read->string('evidence'), $read->time('submitted_at'), $read->enum('state', ProofState::class), $votes['approvals'], $votes['rejections']);
    }
}
