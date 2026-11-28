# Days and dates

## Days

A `Day` is a day of one calendar as a whole number: 0 is the first day of year 1, 1 the next, -1 the last day of
year 0. Two days are compared by their methods — never with `==` — and only within one calendar:

```php
$day->isBefore($other);
$day->isAfter($other);
$day->isSameDay($other);
$day->compare($other);         // -1, 0 or 1
$day->plus(30);                // thirty days later
$day->daysSince($other);       // the days from the other to this one
```

Comparing or subtracting days of two calendars is refused with `CalendarMismatch`: convert one first, see
[Linked calendars](linked-calendars.md).

## Dates

A `Date` is a year, a month and a day of the month, as the calendar reads them; the month and the day count from 1,
the year may be 0 or negative.

```php
$day = $calendar->day(412, 4, 15);             // also dayOf(new Date(412, 4, 15))
$date = $calendar->date($day);                 // Date(412, 4, 15)
$date->compare($other);                        // by year, then month, then day
$date->compareWithinYear($other);              // by month, then day
```

A date that does not exist — the 31st of a month of 30 days, a leap day in a common year — is refused with
`InvalidCalendar`.

## Years and months

| Method of `Calendar` | Gives |
|---|---|
| `isLeap($year)` | Whether the year is leap |
| `yearLength($year)` | The days of the year |
| `monthLength($year, $month)` | The days of a month that year |
| `month($month)` | The `Month` of that number |

## Weekdays

`weekday($day)` gives the weekday from 0, `weekdayName($day)` its name; both are null for a day of a month outside the
week. The days outside the week do not move it: the day after a festival has the weekday after the one before it.

## Years between two days

`yearsBetween($from, $to)` gives the whole years from one day to another: one more each time the month and day of the
first are reached or passed. Someone born on a leap day has their anniversary on the day after the end of that month
in a common year. See [Time and ages](time-and-ages.md).

## Bounds

Years lie from -1 000 000 to 1 000 000. A day outside them, or a year beyond, is refused with `OutOfRange`. Within
them, turning a day into a date is a division and at most a few corrections, whatever the year.
