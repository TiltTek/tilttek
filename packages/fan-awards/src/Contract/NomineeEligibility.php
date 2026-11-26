<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Contract;

use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Nominee;

/**
 * Whether a nominee may stand in a category: published in the award year, of the category's kind, not a winner of the
 * past editions — the application's rules. Asked when an organiser proposes a nominee and when a voter writes one in.
 *
 * An implementation answers from the server's own records and keeps no state between two calls.
 */
interface NomineeEligibility
{
    /**
     * Null when the nominee is eligible; otherwise the reason, shown to the voter.
     */
    public function refusal(Edition $edition, Category $category, Nominee $nominee): ?string;
}
