<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Tilttek\Activity\Activity;

/**
 * A criterion that reads the activity itself: each activity it accepts brings it a contribution.
 */
interface Leaf extends Criterion
{
    public function accepts(Activity $activity): bool;

    /**
     * The verbs it reads, as patterns.
     *
     * @return list<string>
     */
    public function patterns(): array;
}
