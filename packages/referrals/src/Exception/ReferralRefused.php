<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Exception;

use DomainException;
use Tilttek\Referrals\Refusal;

/**
 * A referral, or its reward, that the guards do not allow, with the reason, for the application to tell the member in
 * its own words.
 */
final class ReferralRefused extends DomainException implements ReferralsException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(match ($refusal) {
            Refusal::SelfReferral => 'A member cannot refer themselves.',
            Refusal::NewMember => 'The referrer has not been a member long enough to refer others.',
            Refusal::NotNewcomer => 'Only a member who has just joined can be referred.',
            Refusal::AlreadyReferred => 'This member was already referred by another member.',
            Refusal::NoFirstAction => 'The referred member has not made a first real action since the referral.',
        });
    }

    public static function because(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
