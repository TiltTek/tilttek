<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeZone;
use Override;
use Tilttek\Activity\Activity;

/**
 * One timezone for every member: a site whose members live in the same one.
 */
final readonly class FixedTimezone implements TimezoneResolver
{
    public function __construct(
        private DateTimeZone $timezone,
    ) {
    }

    #[Override]
    public function timezoneFor(Activity $activity): DateTimeZone
    {
        return $this->timezone;
    }
}
