<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Exception;

use RuntimeException;

/**
 * The store refused every code drawn for a member, each already given to another: a store that answers wrongly,
 * since 50 random bits make a collision rare.
 */
final class ReferralConflict extends RuntimeException implements ReferralsException
{
    public static function noFreeCode(int $attempts): self
    {
        return new self(\sprintf('The %d referral codes drawn all belong to other members.', $attempts));
    }
}
