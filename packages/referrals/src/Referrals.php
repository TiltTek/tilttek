<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use DateInterval;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Random\Randomizer;
use Tilttek\Exchange\MemberSince;
use Tilttek\Exchange\NoMemberSince;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Points;
use Tilttek\Referrals\Contract\FirstActions;
use Tilttek\Referrals\Contract\NoSamePersonSignal;
use Tilttek\Referrals\Contract\ReferralStore;
use Tilttek\Referrals\Contract\SamePerson;
use Tilttek\Referrals\Exception\InvalidReferral;
use Tilttek\Referrals\Exception\ReferralConflict;
use Tilttek\Referrals\Exception\ReferralRefused;
use Tilttek\Referrals\Exception\UnknownReferralCode;

/**
 * The referrals of an application, in one object: a member's code, a newcomer referred by it, and the reward in points
 * of both, paid once the newcomer has made a first real action and only once.
 *
 * ```php
 * $code = $referrals->code($member);                  // shared in a link the application builds
 * $referrals->refer($code->value, $newcomer);         // when the newcomer joins through the link
 * $referrals->reward($newcomer);                      // when the newcomer plays a first game
 * ```
 *
 * The points are given by `tilttek/points`, under keys built from the referral's identifier: the same reward asked
 * twice, or by two processes at once, gives the points once.
 */
final readonly class Referrals
{
    /**
     * How many codes are drawn for a member before giving up, each refused by the store as another member's.
     */
    public const int CODE_ATTEMPTS = 5;

    public const int MAX_READ = 100;

    public function __construct(
        private ReferralStore $store,
        private Points $points,
        private FirstActions $firstActions,
        private ClockInterface $clock,
        private Terms $terms = new Terms(),
        private MemberSince $memberSince = new NoMemberSince(),
        private SamePerson $samePerson = new NoSamePersonSignal(),
        private Randomizer $randomizer = new Randomizer(),
    ) {
    }

    /**
     * The member's referral code, drawn the first time it is asked for.
     *
     * @throws ReferralConflict when the store refuses every code drawn
     */
    public function code(ResourceReference $member): ReferralCode
    {
        $code = $this->store->codeOf($member);

        for ($attempt = 1; !$code instanceof ReferralCode && $attempt <= self::CODE_ATTEMPTS; ++$attempt) {
            $code = $this->store->addCode($member, ReferralCode::generate($this->randomizer));
        }

        return $code ?? throw ReferralConflict::noFreeCode(self::CODE_ATTEMPTS);
    }

    /**
     * Records that the newcomer joined through the code. The same newcomer referred again by the same member is the
     * same referral, returned as it is.
     *
     * @throws InvalidReferral     when the text is not a code
     * @throws UnknownReferralCode when the code belongs to no member
     * @throws ReferralRefused     when a guard refuses it: the member themselves, a referrer too new, a newcomer who
     *                             joined too long ago, a newcomer referred by another member
     */
    public function refer(string $code, ResourceReference $newcomer): Referral
    {
        $referrer = $this->store->memberOf(ReferralCode::fromString($code)) ?? throw UnknownReferralCode::create();

        if ($referrer->equals($newcomer) || $this->samePerson->same($referrer, $newcomer)) {
            throw ReferralRefused::because(Refusal::SelfReferral);
        }

        $existing = $this->store->ofNewcomer($newcomer);

        if (!$existing instanceof Referral) {
            $now = $this->clock->now();
            $this->checkDates($referrer, $newcomer, $now);
            $existing = $this->store->add(new Referral(ReferralId::generate($this->randomizer), $referrer, $newcomer, $now));
        }

        if (!$existing->referrer->equals($referrer)) {
            throw ReferralRefused::because(Refusal::AlreadyReferred);
        }

        return $existing;
    }

    /**
     * Rewards the newcomer's referral, once they have made a first real action at or after it: the newcomer receives
     * their points, and the referrer theirs within the cap. Asked again, it gives nothing more. Returns null when the
     * member was not referred.
     *
     * @throws ReferralRefused  when the newcomer has made no first action since the referral, or the application tells
     *                          the two members are one person
     * @throws MovementConflict when a key of the referral is written in the ledger for other points
     */
    public function reward(ResourceReference $newcomer): ?Reward
    {
        $referral = $this->store->ofNewcomer($newcomer);

        if (ReferralStatus::Pending === $referral?->status) {
            $referral = $this->settle($referral);
        }

        if (!$referral instanceof Referral) {
            return null;
        }

        if (ReferralStatus::Refused === $referral->status) {
            throw ReferralRefused::because(Refusal::SelfReferral);
        }

        $newcomerGrant = $this->grant($referral, $referral->newcomer, $referral->newcomerPoints, 'newcomer');
        $referrerGrant = $this->grant($referral, $referral->referrer, $referral->referrerPoints, 'referrer');

        return new Reward($referral, $referral->referrerPoints, $referral->newcomerPoints, $newcomerGrant || $referrerGrant);
    }

    public function referralOf(ResourceReference $newcomer): ?Referral
    {
        return $this->store->ofNewcomer($newcomer);
    }

    /**
     * The referrals the member made, oldest first.
     *
     * @return list<Referral>
     *
     * @throws InvalidReferral when the offset is negative or the limit is not 1 to 100
     */
    public function referralsOf(ResourceReference $referrer, int $offset = 0, int $limit = 50): array
    {
        if ($offset < 0 || $limit < 1 || $limit > self::MAX_READ) {
            throw InvalidReferral::because(\sprintf('A read starts at 0 or later and holds 1 to %d referrals.', self::MAX_READ));
        }

        return $this->store->ofReferrer($referrer, $offset, $limit);
    }

    /**
     * How many more referrals can give the member points before the cap.
     */
    public function remaining(ResourceReference $referrer): int
    {
        return max(0, $this->terms->maxRewards - $this->store->rewardedCount($referrer));
    }

    /**
     * Erases the member's referrals: no code or referral links them to another member afterwards. Their points are
     * erased by `tilttek/points`.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return $this->store->eraseMember($member);
    }

    /**
     * @throws ReferralRefused
     */
    private function settle(Referral $referral): ?Referral
    {
        $action = $this->firstActions->firstActionOf($referral->newcomer);

        if (!$action instanceof FirstAction || $action->at < $referral->referredAt) {
            throw ReferralRefused::because(Refusal::NoFirstAction);
        }

        $now = $this->clock->now();

        if (!Erasure::isErased($referral->referrer) && $this->samePerson->same($referral->referrer, $referral->newcomer)) {
            return $this->store->refuse($referral->id, $now);
        }

        return $this->store->settle($referral->id, $this->terms->referrerPoints, $this->terms->newcomerPoints, $this->terms->maxRewards, $now);
    }

    /**
     * Whether a movement was written: none for no points, or for a side erased since.
     */
    private function grant(Referral $referral, ResourceReference $member, int $points, string $side): bool
    {
        if (0 === $points || Erasure::isErased($member)) {
            return false;
        }

        return $this->points->grant($member, $points, 'referral:'.$referral->id->value.':'.$side, $this->terms->source, $this->terms->currency)->new;
    }

    /**
     * @throws ReferralRefused
     */
    private function checkDates(ResourceReference $referrer, ResourceReference $newcomer, DateTimeImmutable $now): void
    {
        if ($this->terms->referrerSeniority instanceof DateInterval) {
            $joinedAt = $this->memberSince->joinedAt($referrer);

            if (!$joinedAt instanceof DateTimeImmutable || $joinedAt > $now->sub($this->terms->referrerSeniority)) {
                throw ReferralRefused::because(Refusal::NewMember);
            }
        }

        if ($this->terms->newcomerWindow instanceof DateInterval) {
            $joinedAt = $this->memberSince->joinedAt($newcomer);

            if (!$joinedAt instanceof DateTimeImmutable || $joinedAt < $now->sub($this->terms->newcomerWindow)) {
                throw ReferralRefused::because(Refusal::NotNewcomer);
            }
        }
    }
}
