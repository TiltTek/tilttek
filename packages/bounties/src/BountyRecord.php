<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * The reading of the claims and awards of a bounty from storage, and the checks of a bounty as a whole: what each move
 * of `Bounty` keeps true, a record must hold too.
 *
 * @internal
 */
final class BountyRecord
{
    private const array CLAIM_FIELDS = ['id', 'claimant', 'evidence', 'claimed_at'];

    private const array AWARD_FIELDS = ['id', 'claim', 'amount', 'awarded_at'];

    private function __construct()
    {
    }

    /**
     * @throws InvalidBounty
     */
    public static function claim(mixed $record): Claim
    {
        $read = new Reader(self::record($record, 'claims'), 'a claim', self::CLAIM_FIELDS);

        return new Claim($read->string('id'), $read->member('claimant'), $read->string('evidence'), $read->time('claimed_at'));
    }

    /**
     * @throws InvalidBounty
     */
    public static function award(mixed $record): Award
    {
        $read = new Reader(self::record($record, 'awards'), 'an award', self::AWARD_FIELDS);

        return new Award($read->string('id'), $read->string('claim'), $read->int('amount'), $read->time('awarded_at'));
    }

    /**
     * @throws InvalidBounty
     */
    public static function verify(Bounty $bounty): void
    {
        if ($bounty->endsAt <= $bounty->openedAt || $bounty->graceEndsAt < $bounty->endsAt) {
            throw InvalidBounty::because('A bounty ends after it is offered, and its grace ends once it ends or later.');
        }

        if ($bounty->version < 1) {
            throw InvalidBounty::because('A bounty is at version 1 or later.');
        }

        self::verifyClaims($bounty);
        self::verifyAwards($bounty);
        self::verifyOutcome($bounty);
    }

    /**
     * @throws InvalidBounty
     */
    private static function verifyClaims(Bounty $bounty): void
    {
        $seen = [];
        $own = [];

        foreach ($bounty->claims as $claim) {
            $claimant = $claim->claimant->toString();
            $own[$claimant] = ($own[$claimant] ?? 0) + 1;

            if (isset($seen[$claim->id]) || $claim->claimant->equals($bounty->sponsor) || $own[$claimant] > Bounty::MAX_CLAIMS_PER_MEMBER) {
                throw InvalidBounty::because(\sprintf('The claim "%s" is listed once, by a member other than the sponsor, one of at most %d of theirs.', $claim->id, Bounty::MAX_CLAIMS_PER_MEMBER));
            }

            $seen[$claim->id] = true;

            if ($claim->claimedAt < $bounty->openedAt || $claim->claimedAt >= $bounty->endsAt) {
                throw InvalidBounty::because(\sprintf('The claim "%s" was made while the bounty was open to claims.', $claim->id));
            }
        }
    }

    /**
     * @throws InvalidBounty
     */
    private static function verifyAwards(Bounty $bounty): void
    {
        $seen = [];

        foreach ($bounty->awards as $award) {
            if (isset($seen[$award->id]) || !$bounty->claim($award->claim) instanceof Claim) {
                throw InvalidBounty::because(\sprintf('The award "%s" is listed once, to a claim of the bounty.', $award->id));
            }

            $seen[$award->id] = true;

            if ($award->awardedAt < $bounty->openedAt || $award->awardedAt >= $bounty->graceEndsAt) {
                throw InvalidBounty::because(\sprintf('The award "%s" was given while the bounty could be awarded.', $award->id));
            }
        }

        if ($bounty->awarded() > $bounty->prize->amount) {
            throw InvalidBounty::because('The awards of a bounty are no more than its amount.');
        }
    }

    /**
     * @throws InvalidBounty
     */
    private static function verifyOutcome(Bounty $bounty): void
    {
        if ($bounty->state->isSettled() !== ($bounty->settledAt instanceof DateTimeImmutable)) {
            throw InvalidBounty::because('A bounty has a settlement time when, and only when, its points are settled.');
        }

        if ($bounty->settledAt instanceof DateTimeImmutable && $bounty->settledAt < $bounty->openedAt) {
            throw InvalidBounty::because('A bounty is settled after it is offered.');
        }

        $valid = match ($bounty->state) {
            BountyState::Open => $bounty->awarded() < $bounty->prize->amount,
            BountyState::Awarded => [] !== $bounty->awards,
            BountyState::Expired => [] === $bounty->awards,
            BountyState::Cancelled => [] === $bounty->claims,
        };

        if (!$valid) {
            throw InvalidBounty::because(\sprintf('A bounty %s has no awards when it expired, no claims when it was cancelled, some when it was awarded, and some points left while it is open.', $bounty->state->value));
        }
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidBounty
     */
    private static function record(mixed $record, string $what): array
    {
        if (!\is_array($record)) {
            throw InvalidBounty::because(\sprintf('The %s of a bounty are records.', $what));
        }

        return $record;
    }
}
