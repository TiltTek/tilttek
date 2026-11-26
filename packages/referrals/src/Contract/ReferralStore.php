<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Referrals\Erasure;
use Tilttek\Referrals\Referral;
use Tilttek\Referrals\ReferralCode;
use Tilttek\Referrals\ReferralId;

/**
 * Where the codes and the referrals are kept. A store backed by a database holds a unique constraint on the code, on
 * the member of a code and on the newcomer of a referral, and settles a referral in one step that another process
 * cannot slip between, so that the cap holds when two newcomers of one referrer act at the same moment.
 */
interface ReferralStore
{
    public function codeOf(ResourceReference $member): ?ReferralCode;

    /**
     * Keeps the code for the member, unless they hold one already, which is returned; null, keeping nothing, when the
     * code belongs to another member.
     */
    public function addCode(ResourceReference $member, ReferralCode $code): ?ReferralCode;

    public function memberOf(ReferralCode $code): ?ResourceReference;

    /**
     * Keeps the referral, unless its newcomer was referred already: the referral kept then is returned.
     */
    public function add(Referral $referral): Referral;

    public function ofNewcomer(ResourceReference $newcomer): ?Referral;

    /**
     * The referrals the member made, oldest first, from an offset.
     *
     * @return list<Referral>
     */
    public function ofReferrer(ResourceReference $referrer, int $offset, int $limit): array;

    /**
     * How many of the member's referrals gave them points.
     */
    public function rewardedCount(ResourceReference $referrer): int;

    /**
     * Settles the pending referral as rewarded, in one step: the newcomer receives their points, and the referrer
     * theirs only while fewer than `maxRewards` of their referrals gave them points, and never when the referrer is
     * `erased:member`. A referral no longer pending is returned as it stands; null when none is kept under the
     * identifier.
     */
    public function settle(ReferralId $id, int $referrerPoints, int $newcomerPoints, int $maxRewards, DateTimeImmutable $at): ?Referral;

    /**
     * Settles the pending referral as refused; a referral no longer pending is returned as it stands, null when none
     * is kept under the identifier.
     */
    public function refuse(ReferralId $id, DateTimeImmutable $at): ?Referral;

    /**
     * Deletes the member's code and their pending referrals as a newcomer, and writes `erased:member` in their place
     * in their other referrals, on either side.
     */
    public function eraseMember(ResourceReference $member): Erasure;
}
