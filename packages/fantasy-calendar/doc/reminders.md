# Reminders

## Reminders

A `Reminder` ties an entry of the application to a day of a calendar: its identifier, the identifier of the entry —
a character, a city, a battle, opaque to the library —, the day, and its `Recurrence`:

| Recurrence | Comes back |
|---|---|
| `Once` | Never |
| `Yearly` | Every year on its month and day, from its day on; a year without that day skips it |
| `Monthly` | Every month on its day, from its day on; a month without that day skips it |

```php
new Reminder('r-founding', 'city-17', $calendar->day(1, 4, 15), Recurrence::Yearly);
```

## The agenda

`Agenda` lists the reminders that fall in a range of days of a calendar — its own, and those of its parents converted:

```php
$agenda = new Agenda($world, $reminders);
foreach ($agenda->between($from, $to) as $occurrence) {
    $occurrence->reminder;      // the reminder
    $occurrence->day;           // its day in the calendar asked for
    $occurrence->isInherited(); // whether it belongs to a parent calendar
}
```

- The occurrences come by day, then the calendar's own before those of its parents, then by reminder identifier.
- A range holds 1 to 3 660 days, both ends included, in one calendar.
- The agenda keeps no state between two calls.

## The reminder port

`Contract\ReminderSource` is where the reminders are kept — the database of the application, usually. The agenda asks
it, for each calendar, the reminders that may fall in the range:

```php
public function reminders(string $calendar, Day $from, Day $to): iterable;
```

The source returns at least every reminder of the calendar on a day of the range, and every recurring reminder of the
calendar whose first day is on or before the end of the range. It may return more: the agenda keeps only the days that
match.

`Store\InMemoryReminders` keeps reminders in memory, for tests and for a calendar read whole from a file: `add()`,
`remove()`, and the port.
