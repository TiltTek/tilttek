<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeZone;
use Exception;
use Override;
use Tilttek\Activity\Activity;

/**
 * The member's timezone as the application writes it in the activity's context — `timezone: 'Europe/Paris'` —, the
 * fallback when the context has none or an unknown one. Kept with the activity, it stays the same in a rebuild even
 * after the member moves.
 *
 * The application writes it from the member's profile on the server, never from a value the client sends: a client
 * that chooses the timezone of each gesture can move its day anywhere in a 26-hour range, and keep a daily streak alive
 * while acting every second day.
 */
final readonly class ContextTimezone implements TimezoneResolver
{
    public function __construct(
        private string $key,
        private DateTimeZone $fallback,
    ) {
    }

    #[Override]
    public function timezoneFor(Activity $activity): DateTimeZone
    {
        $name = $activity->context->get($this->key);

        try {
            return \is_string($name) ? new DateTimeZone($name) : $this->fallback;
        } catch (Exception) {
            return $this->fallback;
        }
    }
}
