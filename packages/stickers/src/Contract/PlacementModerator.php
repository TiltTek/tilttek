<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Contract;

use Tilttek\Stickers\Placement;
use Tilttek\Stickers\Verdict;

/**
 * The moderation of a new placement, before it is stored: a filter of the site, a queue of review, a holder under
 * watch. A placement hidden by the verdict is stored and shown to no one until moderation restores it.
 */
interface PlacementModerator
{
    public function review(Placement $placement): Verdict;
}
