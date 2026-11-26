<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Eligibility;

use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\Contract\NomineeEligibility;
use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Nominee;

/**
 * Every nominee is eligible, until the application gives its own rules.
 */
final readonly class AnyNominee implements NomineeEligibility
{
    public function refusal(Edition $edition, Category $category, Nominee $nominee): ?string
    {
        return null;
    }
}
