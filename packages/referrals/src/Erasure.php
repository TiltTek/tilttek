<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use Phil\Resource\ResourceReference;

/**
 * What erasing a member did: their code deleted, their pending referrals as a newcomer deleted, and `erased:member`
 * written in their place in the other referrals, which still count towards the referrer's cap and still let the other
 * member's reward be paid. No referral then links the erased member to anyone.
 */
final readonly class Erasure
{
    /**
     * The reference that stands for an erased member, as in `tilttek/points` and `tilttek/exchange`.
     */
    public const string ERASED = 'erased:member';

    public function __construct(
        public int $codes = 0,
        public int $deleted = 0,
        public int $detached = 0,
    ) {
    }

    public static function erasedMember(): ResourceReference
    {
        return ResourceReference::fromString(self::ERASED);
    }

    public static function isErased(ResourceReference $member): bool
    {
        return self::ERASED === $member->toString();
    }
}
