<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Contract;

use Phil\Resource\ResourceReference;

/**
 * Tells whether two accounts belong to one person, from the signals the application keeps — the same verified
 * address, the same device —, to refuse a member referring themselves through a second account. The library never
 * reads those signals.
 */
interface SamePerson
{
    public function same(ResourceReference $referrer, ResourceReference $newcomer): bool;
}
