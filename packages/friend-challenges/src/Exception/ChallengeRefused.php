<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Exception;

use DomainException;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\ChallengeState;

/**
 * An operation the rules of the challenge do not allow: the wrong member, the wrong state or a time past its limit.
 * Nothing is written and no point moves when one is refused.
 */
final class ChallengeRefused extends DomainException implements FriendChallengesException
{
    public static function unknown(string $id): self
    {
        return new self(\sprintf('No challenge "%s" is stored.', $id));
    }

    public static function idReused(string $id): self
    {
        return new self(\sprintf('The challenge "%s" exists already, with other values; an identifier names one challenge.', $id));
    }

    public static function notFriend(ResourceReference $creator, ResourceReference $member): self
    {
        return new self(\sprintf('%s is not a friend of %s, who may not invite them.', $member->toString(), $creator->toString()));
    }

    public static function notInvited(string $id, ResourceReference $member): self
    {
        return new self(\sprintf('%s is not invited to the challenge "%s", which is between friends.', $member->toString(), $id));
    }

    public static function creatorTakesPart(string $id): self
    {
        return new self(\sprintf('The creator of the challenge "%s" sets it and judges it; they do not take part.', $id));
    }

    public static function notParticipant(string $id, ResourceReference $member): self
    {
        return new self(\sprintf('%s does not take part in the challenge "%s".', $member->toString(), $id));
    }

    public static function notCreator(string $id, ResourceReference $member): self
    {
        return new self(\sprintf('Only the creator of the challenge "%s" may do that; %s is not.', $id, $member->toString()));
    }

    public static function notModerator(ResourceReference $member): self
    {
        return new self(\sprintf('Only a moderator of the site may do that; %s is not one.', $member->toString()));
    }

    public static function notVoter(string $id, ResourceReference $member): self
    {
        return new self(\sprintf('%s may not vote on this proof of the challenge "%s": the creator and the other participants vote.', $member->toString(), $id));
    }

    public static function notJudge(string $id, ResourceReference $member): self
    {
        return new self(\sprintf('%s does not judge the proofs of the challenge "%s".', $member->toString(), $id));
    }

    public static function voteChanged(string $id, string $proof, ResourceReference $member): self
    {
        return new self(\sprintf('%s voted already on the proof "%s" of the challenge "%s", the other way.', $member->toString(), $proof, $id));
    }

    public static function inState(string $id, ChallengeState $state, string $what): self
    {
        return new self(\sprintf('The challenge "%s" is %s: %s.', $id, $state->value, $what));
    }

    public static function closed(string $id, string $what): self
    {
        return new self(\sprintf('The time to %s in the challenge "%s" is over.', $what, $id));
    }

    public static function notDue(string $id): self
    {
        return new self(\sprintf('The challenge "%s" is still open to proofs or to their review.', $id));
    }

    public static function full(string $id, string $what, int $most): self
    {
        return new self(\sprintf('The challenge "%s" holds %d %s, the most it takes.', $id, $most, $what));
    }

    public static function unknownProof(string $id, string $proof): self
    {
        return new self(\sprintf('The challenge "%s" has no proof "%s".', $id, $proof));
    }

    public static function proofReused(string $id, string $proof): self
    {
        return new self(\sprintf('The proof "%s" of the challenge "%s" exists already, with other values.', $proof, $id));
    }

    public static function proofDecided(string $id, string $proof): self
    {
        return new self(\sprintf('The proof "%s" of the challenge "%s" is decided already.', $proof, $id));
    }

    public static function proofsSubmitted(string $id): self
    {
        return new self(\sprintf('The challenge "%s" has proofs: only a moderator may withdraw it now.', $id));
    }
}
