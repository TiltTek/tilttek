<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Store;

use Tilttek\FantasyCalendar\Contract\ReminderSource;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Reminder;

/**
 * The reminders kept in memory, for tests and for a calendar read whole from a file.
 */
final class InMemoryReminders implements ReminderSource
{
    /**
     * @var array<string, Reminder> by identifier
     */
    private array $reminders = [];

    public function add(Reminder $reminder): void
    {
        $this->reminders[$reminder->id] = $reminder;
    }

    public function remove(string $id): void
    {
        unset($this->reminders[$id]);
    }

    public function reminders(string $calendar, Day $from, Day $to): iterable
    {
        foreach ($this->reminders as $reminder) {
            if ($reminder->day->calendar === $calendar && $reminder->day->number <= $to->number) {
                yield $reminder;
            }
        }
    }
}
