<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

/**
 * Where a referral stands: waiting for the newcomer's first real action, rewarded, or refused once the application
 * told the two members are one person.
 */
enum ReferralStatus: string
{
    case Pending = 'pending';

    case Rewarded = 'rewarded';

    case Refused = 'refused';
}
