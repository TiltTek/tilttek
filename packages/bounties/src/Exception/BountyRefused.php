<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Exception;

use DomainException;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\BountyState;

/**
 * An operation the rules of the bounty do not allow: the wrong member, the wrong state, a time past its limit or an
 * amount over what is left. Nothing is written and no point moves when one is refused.
 */
final class BountyRefused extends DomainException implements BountiesException
{
    public static function unknown(string $id): self
    {
        return new self(\sprintf('No bounty "%s" is stored.', $id));
    }

    public static function idReused(string $id): self
    {
        return new self(\sprintf('The bounty "%s" exists already, with other values; an identifier names one bounty.', $id));
    }

    public static function subjectTaken(ResourceReference $subject, string $open): self
    {
        return new self(\sprintf('%s has an open bounty already, "%s": one bounty at a time.', $subject->toString(), $open));
    }

    public static function tooManyOpen(ResourceReference $sponsor, int $most): self
    {
        return new self(\sprintf('%s sponsors %d open bounties, the most the site allows at once.', $sponsor->toString(), $most));
    }

    public static function notSponsor(string $id, ResourceReference $member): self
    {
        return new self(\sprintf('Only the sponsor of the bounty "%s" may do that; %s is not.', $id, $member->toString()));
    }

    public static function sponsorClaims(string $id): self
    {
        return new self(\sprintf('The sponsor of the bounty "%s" does not claim it.', $id));
    }

    public static function inState(string $id, BountyState $state, string $what): self
    {
        return new self(\sprintf('The bounty "%s" is %s: %s.', $id, $state->value, $what));
    }

    public static function closed(string $id, string $what): self
    {
        return new self(\sprintf('The time to %s on the bounty "%s" is over.', $what, $id));
    }

    public static function notDue(string $id): self
    {
        return new self(\sprintf('The bounty "%s" is still open to claims or awards.', $id));
    }

    public static function full(string $id, string $what, int $most): self
    {
        return new self(\sprintf('The bounty "%s" holds %d %s, the most it takes.', $id, $most, $what));
    }

    public static function unknownClaim(string $id, string $claim): self
    {
        return new self(\sprintf('The bounty "%s" has no claim "%s".', $id, $claim));
    }

    public static function claimReused(string $id, string $claim): self
    {
        return new self(\sprintf('The claim "%s" on the bounty "%s" exists already, with other values.', $claim, $id));
    }

    public static function awardReused(string $id, string $award): self
    {
        return new self(\sprintf('The award "%s" of the bounty "%s" exists already, with other values.', $award, $id));
    }

    public static function overPrize(string $id, int $left, int $amount): self
    {
        return new self(\sprintf('The bounty "%s" has %d points left to award; %d is too many.', $id, $left, $amount));
    }

    public static function claimsMade(string $id): self
    {
        return new self(\sprintf('The bounty "%s" has claims: it is awarded, or it expires.', $id));
    }

    public static function nothingAwarded(string $id): self
    {
        return new self(\sprintf('The bounty "%s" has no award to pay: it is cancelled before any claim, or it expires.', $id));
    }
}
