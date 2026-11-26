<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Referrals\Contract\ReferralStore;
use Tilttek\Referrals\Erasure;
use Tilttek\Referrals\Referral;
use Tilttek\Referrals\ReferralCode;
use Tilttek\Referrals\ReferralId;
use Tilttek\Referrals\ReferralStatus;

/**
 * The store in memory, for tests and for a process that keeps nothing.
 */
final class InMemoryReferralStore implements ReferralStore
{
    /**
     * @var array<string, ReferralCode> by member reference
     */
    private array $codes = [];

    /**
     * @var array<string, Referral> by identifier, in the order they were added
     */
    private array $referrals = [];

    #[Override]
    public function codeOf(ResourceReference $member): ?ReferralCode
    {
        return $this->codes[$member->toString()] ?? null;
    }

    #[Override]
    public function addCode(ResourceReference $member, ReferralCode $code): ?ReferralCode
    {
        $kept = $this->codeOf($member);

        if ($kept instanceof ReferralCode) {
            return $kept;
        }

        if ($this->memberOf($code) instanceof ResourceReference) {
            return null;
        }

        return $this->codes[$member->toString()] = $code;
    }

    #[Override]
    public function memberOf(ReferralCode $code): ?ResourceReference
    {
        foreach ($this->codes as $member => $kept) {
            if ($kept->equals($code)) {
                return ResourceReference::fromString($member);
            }
        }

        return null;
    }

    #[Override]
    public function add(Referral $referral): Referral
    {
        return $this->ofNewcomer($referral->newcomer) ?? $this->referrals[$referral->id->value] = $referral;
    }

    #[Override]
    public function ofNewcomer(ResourceReference $newcomer): ?Referral
    {
        foreach ($this->referrals as $referral) {
            if ($referral->newcomer->equals($newcomer)) {
                return $referral;
            }
        }

        return null;
    }

    #[Override]
    public function ofReferrer(ResourceReference $referrer, int $offset, int $limit): array
    {
        $made = array_filter($this->referrals, static fn (Referral $referral): bool => $referral->referrer->equals($referrer));

        return \array_slice(array_values($made), $offset, $limit);
    }

    #[Override]
    public function rewardedCount(ResourceReference $referrer): int
    {
        return \count(array_filter($this->referrals, static fn (Referral $referral): bool => $referral->referrer->equals($referrer) && $referral->referrerPoints > 0));
    }

    #[Override]
    public function settle(ReferralId $id, int $referrerPoints, int $newcomerPoints, int $maxRewards, DateTimeImmutable $at): ?Referral
    {
        $referral = $this->referrals[$id->value] ?? null;

        if (!$referral instanceof Referral || ReferralStatus::Pending !== $referral->status) {
            return $referral;
        }

        $within = !Erasure::isErased($referral->referrer) && $this->rewardedCount($referral->referrer) < $maxRewards;

        return $this->referrals[$id->value] = $referral->rewarded($at, $within ? $referrerPoints : 0, $newcomerPoints);
    }

    #[Override]
    public function refuse(ReferralId $id, DateTimeImmutable $at): ?Referral
    {
        $referral = $this->referrals[$id->value] ?? null;

        if (!$referral instanceof Referral || ReferralStatus::Pending !== $referral->status) {
            return $referral;
        }

        return $this->referrals[$id->value] = $referral->refused($at);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        $codes = isset($this->codes[$member->toString()]) ? 1 : 0;
        unset($this->codes[$member->toString()]);
        $deleted = 0;
        $detached = 0;
        $erased = Erasure::erasedMember();

        foreach ($this->referrals as $id => $referral) {
            if ($referral->newcomer->equals($member) && ReferralStatus::Pending === $referral->status) {
                unset($this->referrals[$id]);
                ++$deleted;

                continue;
            }

            if ($referral->newcomer->equals($member)) {
                $referral = $referral->withNewcomer($erased);
                ++$detached;
            }

            if ($referral->referrer->equals($member)) {
                $referral = $referral->withReferrer($erased);
                ++$detached;
            }

            $this->referrals[$id] = $referral;
        }

        return new Erasure($codes, $deleted, $detached);
    }
}
