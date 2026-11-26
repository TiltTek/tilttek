<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Tilttek\Referrals\Exception\InvalidReferral;

/**
 * A newcomer referred by a member, and where it stands. Once settled, it keeps the points each side was given — the
 * referrer's are 0 past the cap or when the referrer was erased —, so that a reward asked again pays the same amounts
 * whatever the terms became meanwhile. The moments are kept in UTC.
 */
final readonly class Referral
{
    public DateTimeImmutable $referredAt;

    public ?DateTimeImmutable $settledAt;

    public function __construct(
        public ReferralId $id,
        public ResourceReference $referrer,
        public ResourceReference $newcomer,
        DateTimeImmutable $referredAt,
        public ReferralStatus $status = ReferralStatus::Pending,
        ?DateTimeImmutable $settledAt = null,
        public int $referrerPoints = 0,
        public int $newcomerPoints = 0,
    ) {
        if ((ReferralStatus::Pending === $status) !== (!$settledAt instanceof DateTimeImmutable)) {
            throw InvalidReferral::because('A referral is settled at a moment exactly when it is no longer pending.');
        }

        if (ReferralStatus::Rewarded !== $status && 0 !== $referrerPoints + $newcomerPoints) {
            throw InvalidReferral::because('Only a rewarded referral gives points.');
        }

        if ($referrerPoints < 0 || $newcomerPoints < 0) {
            throw InvalidReferral::because('A referral gives no negative points.');
        }

        $utc = new DateTimeZone('UTC');
        $this->referredAt = $referredAt->setTimezone($utc);
        $this->settledAt = $settledAt?->setTimezone($utc);
    }

    public function rewarded(DateTimeImmutable $at, int $referrerPoints, int $newcomerPoints): self
    {
        return new self($this->id, $this->referrer, $this->newcomer, $this->referredAt, ReferralStatus::Rewarded, $at, $referrerPoints, $newcomerPoints);
    }

    public function refused(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->referrer, $this->newcomer, $this->referredAt, ReferralStatus::Refused, $at);
    }

    public function withReferrer(ResourceReference $referrer): self
    {
        return new self($this->id, $referrer, $this->newcomer, $this->referredAt, $this->status, $this->settledAt, $this->referrerPoints, $this->newcomerPoints);
    }

    public function withNewcomer(ResourceReference $newcomer): self
    {
        return new self($this->id, $this->referrer, $newcomer, $this->referredAt, $this->status, $this->settledAt, $this->referrerPoints, $this->newcomerPoints);
    }
}
