<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Contract;

use Override;
use Phil\Resource\ResourceReference;

/**
 * The answer when the application keeps no signal: two different references are two people. A member referring
 * themselves under one reference is still refused.
 */
final readonly class NoSamePersonSignal implements SamePerson
{
    #[Override]
    public function same(ResourceReference $referrer, ResourceReference $newcomer): bool
    {
        return false;
    }
}
