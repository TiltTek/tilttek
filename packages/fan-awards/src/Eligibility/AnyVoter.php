<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Eligibility;

use Phil\Resource\ResourceReference;
use Tilttek\FanAwards\Contract\VoterEligibility;
use Tilttek\FanAwards\Edition;

/**
 * Every member the application lets reach the ballot may vote, until it gives its own rules.
 */
final readonly class AnyVoter implements VoterEligibility
{
    public function mayVote(ResourceReference $voter, Edition $edition): bool
    {
        return true;
    }
}
