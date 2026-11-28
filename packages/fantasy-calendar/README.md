# tilttek/fantasy-calendar

**Keep time in an imaginary world: its own months, weeks, leap years, moons, eras and seasons, linked calendars, ages,
reminders and a date that moves on.**

tilttek/fantasy-calendar describes the calendars of imaginary worlds and the time that passes in them. A calendar has
its own months — some outside the week, like a festival between two months —, its own week, leap years by any rule of
cycles and exceptions, moons with cycles of their own and their phases, eras that number the years again, and seasons
that give the climate of the day. Every day is a whole number, negative before the first year; a calendar turns it into
a date and back, gives its weekday, its moon phases, its year in the era and its season, for any year within a million
of year 0.

Calendars are linked by a day of the campaign they share, so a day converts from one to another; a child calendar shows
the reminders of its parent. Reminders tie an entry of the application — a character, a city, a battle — to a day, once
or every year or month. The current date of the world moves at the pace the game master chooses, or only when moved;
ages — of a character, of a city — are computed from their start and end. The seasons and the climate zone of a day are
offered through a port for what draws the weather.

It is for an application that keeps a campaign, a world or a game in its own time. It is a plain PHP library whose one
requirement is the `mbstring` extension. A proleptic Gregorian calendar is given as a model to copy; no calendar of a
licensed world is shipped.

## Status

| Part | State |
|---|---|
| `Calendar`, `Month`, `LeapRule`, `LeapCycle`: months, weeks, leap years, days outside the week | Implemented and tested |
| `Day`, `Date`: days as whole numbers, dates, weekdays, negative years | Implemented and tested |
| `Moon`, `Phase`, `Era`, `EraYear`, `Season`, `SeasonAt`, `Contract\SeasonSource`: moons, eras, seasons and climate | Implemented and tested |
| `CalendarCodec`, `Template\Gregorian`: calendars written to arrays and read back, the model to copy | Implemented and tested |
| `Calendars`, `Link`: linked calendars, parents and children, conversions | Implemented and tested |
| `Reminder`, `Recurrence`, `Agenda`, `Contract\ReminderSource`, `Store\InMemoryReminders` | Implemented and tested |
| `CurrentDate`, `Pace`, `Lifespan`: the date that moves on, ages | Implemented and tested |
| Hours and minutes within a day | Not started |
| A store of reminders on Doctrine DBAL, a Symfony bundle, a calendar view | Not started: adapters of the application, or of a future bundle |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Defining a calendar](doc/defining-a-calendar.md) | Months, weeks, leap years, moons, eras, seasons, the array form and the Gregorian model. |
| [Days and dates](doc/days-and-dates.md) | Day numbers, dates, weekdays, comparing days, negative years and bounds. |
| [Moons, eras and seasons](doc/moons-eras-seasons.md) | Moon phases, years in eras, seasons, climate zones and the season port. |
| [Linked calendars](doc/linked-calendars.md) | Calendars joined by a common day, parents and children, conversions. |
| [Reminders](doc/reminders.md) | Reminders once or recurring, the agenda and the reminder port. |
| [Time and ages](doc/time-and-ages.md) | The current date and its pace, ages and lifespans. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `FantasyCalendarException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\FantasyCalendar\CalendarCodec;
use Tilttek\FantasyCalendar\CurrentDate;
use Tilttek\FantasyCalendar\Lifespan;
use Tilttek\FantasyCalendar\Pace;

$calendar = CalendarCodec::fromArray($definition);          // read from the JSON or YAML of the world

$coronation = $calendar->day(412, 4, 15);                 // a Day: a whole number in this calendar
$calendar->date($coronation->plus(100));                   // the date a hundred days later
$calendar->weekdayName($coronation);                       // 'Fireday'
$calendar->moonPhases($coronation);                        // ['silver' => Phase::Full]
$calendar->yearInEra(412);                                // the year 412 of its era
$calendar->seasonOn($coronation);                          // the season, its climate zone, its day and length

$now = new CurrentDate($coronation, time(), Pace::of(1));  // one day of the world for each real day
$today = $now->today(new DateTimeImmutable());

new Lifespan($calendar->day(380, 1, 1))->ageOn($calendar, $today);   // the age of a character
```

## Requirements

PHP 8.5 or later, with the `mbstring` extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
