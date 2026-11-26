<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Exception;

use DomainException;

/**
 * A referral code that belongs to no member: mistyped, or its member was erased. The message does not repeat the code.
 */
final class UnknownReferralCode extends DomainException implements ReferralsException
{
    public static function create(): self
    {
        return new self('The referral code belongs to no member.');
    }
}
