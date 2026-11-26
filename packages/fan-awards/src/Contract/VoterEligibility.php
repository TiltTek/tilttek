<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\FanAwards\Edition;

/**
 * Whether a member may vote in an edition — a member since before the nominations, a member of the community the
 * awards belong to. Never tied to a payment nor a purchase: a fan award is voted by its fans, not bought.
 *
 * An implementation answers from the server's own records and keeps no state between two calls.
 */
interface VoterEligibility
{
    public function mayVote(ResourceReference $voter, Edition $edition): bool;
}
