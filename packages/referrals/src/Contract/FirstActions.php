<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\Referrals\FirstAction;

/**
 * Tells the newcomer's first real action — a game played, a sheet published —, which the reward of a referral waits
 * for. The application answers from its own records; the library never trusts the call that asks for the reward
 * alone, and an account that only signed up has none.
 */
interface FirstActions
{
    /**
     * The member's first real action; null when they have made none.
     */
    public function firstActionOf(ResourceReference $member): ?FirstAction;
}
