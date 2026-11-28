# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: days and dates, months, leap rules and their counts over
negative years, moons and their phases, eras, seasons, a calendar with a festival outside the week and leap-only days,
the array form and its refusals, linked calendars, the agenda with reminders once and recurring, the current date and
its pace, and ages. The Gregorian model is checked against PHP's own dates — day numbers and weekdays — and property
tests turn random days over a million years into dates and back, and every date of forty years into its day and back.
In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/calendar.php` is the fuzz target of `CalendarCodec`: any JSON is refused with the library's exceptions, or
gives a calendar that writes itself back to the same array and whose day 0 goes to its date and back. `composer fuzz`
runs it.

## Benchmarks

`tests/Benchmark/CalendarBench.php` turns a thousand days far from year 1 into dates with their weekday, moon phases
and season, and lists the reminders of the longest range an agenda allows. On PHP 8.5: about 80 ms for the thousand
days, about 11 ms for 3 660 days of agenda among 200 reminders.

## Testing code that keeps time

Give `CurrentDate` a fixed `DateTimeImmutable`, and `Agenda` a `Store\InMemoryReminders`:

```php
$reminders = new InMemoryReminders();
$reminders->add(new Reminder('r1', 'city-17', $calendar->day(1, 4, 15), Recurrence::Yearly));
$agenda = new Agenda(Calendars::empty()->with($calendar), $reminders);
```
