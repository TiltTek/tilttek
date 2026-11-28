<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Contract;

use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Reminder;

/**
 * Where the reminders of the application are kept — its database, usually. The agenda asks it for the reminders of one
 * calendar that may fall in a range, then keeps the days that match.
 */
interface ReminderSource
{
    /**
     * At least every reminder of the calendar on a day of the range, and every recurring reminder of the calendar whose
     * first day is on or before the end of the range; more is allowed, the agenda filters.
     *
     * @return iterable<Reminder>
     */
    public function reminders(string $calendar, Day $from, Day $to): iterable;
}
