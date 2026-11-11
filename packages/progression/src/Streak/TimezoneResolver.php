<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeZone;
use Tilttek\Activity\Activity;

/**
 * The timezone an activity's day is read in: the member's, so that midnight in Montréal does not break a streak in
 * Paris. Read from the activity itself, a rebuild gives the same days.
 */
interface TimezoneResolver
{
    public function timezoneFor(Activity $activity): DateTimeZone;
}
