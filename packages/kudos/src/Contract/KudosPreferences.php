<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Contract;

use Phil\Resource\ResourceReference;

/**
 * Whether a member takes thanks: the choice is the receiver's, from anyone or from one giver.
 */
interface KudosPreferences
{
    public function accepts(ResourceReference $receiver, ResourceReference $giver): bool;
}
