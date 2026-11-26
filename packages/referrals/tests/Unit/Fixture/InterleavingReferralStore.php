<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Referrals\Contract\ReferralStore;
use Tilttek\Referrals\Erasure;
use Tilttek\Referrals\Referral;
use Tilttek\Referrals\ReferralCode;
use Tilttek\Referrals\ReferralId;

/**
 * A store that runs what another process does right after the first read of a newcomer's referral, or right before
 * the first code is kept: the step that slips between a read and the write that follows.
 */
final class InterleavingReferralStore implements ReferralStore
{
    /**
     * @var ?callable(): mixed
     */
    private $meanwhile;

    /**
     * @param 'addCode'|'ofNewcomer' $at
     * @param callable(): mixed      $meanwhile
     */
    public function __construct(
        private readonly ReferralStore $store,
        private readonly string $at,
        callable $meanwhile,
    ) {
        $this->meanwhile = $meanwhile;
    }

    #[Override]
    public function codeOf(ResourceReference $member): ?ReferralCode
    {
        return $this->store->codeOf($member);
    }

    #[Override]
    public function addCode(ResourceReference $member, ReferralCode $code): ?ReferralCode
    {
        $this->meanwhile('addCode');

        return $this->store->addCode($member, $code);
    }

    #[Override]
    public function memberOf(ReferralCode $code): ?ResourceReference
    {
        return $this->store->memberOf($code);
    }

    #[Override]
    public function add(Referral $referral): Referral
    {
        return $this->store->add($referral);
    }

    #[Override]
    public function ofNewcomer(ResourceReference $newcomer): ?Referral
    {
        $referral = $this->store->ofNewcomer($newcomer);
        $this->meanwhile('ofNewcomer');

        return $referral;
    }

    #[Override]
    public function ofReferrer(ResourceReference $referrer, int $offset, int $limit): array
    {
        return $this->store->ofReferrer($referrer, $offset, $limit);
    }

    #[Override]
    public function rewardedCount(ResourceReference $referrer): int
    {
        return $this->store->rewardedCount($referrer);
    }

    #[Override]
    public function settle(ReferralId $id, int $referrerPoints, int $newcomerPoints, int $maxRewards, DateTimeImmutable $at): ?Referral
    {
        return $this->store->settle($id, $referrerPoints, $newcomerPoints, $maxRewards, $at);
    }

    #[Override]
    public function refuse(ReferralId $id, DateTimeImmutable $at): ?Referral
    {
        return $this->store->refuse($id, $at);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        return $this->store->eraseMember($member);
    }

    private function meanwhile(string $at): void
    {
        if ($at === $this->at && null !== $this->meanwhile) {
            $meanwhile = $this->meanwhile;
            $this->meanwhile = null;
            $meanwhile();
        }
    }
}
