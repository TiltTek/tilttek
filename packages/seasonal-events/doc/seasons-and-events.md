# Seasons and events

## Schedules

A `SeasonSchedule` says when a season comes, in its timezone:

```php
SeasonSchedule::once('America/Toronto', '2027-03-20', '2027-04-03');            // once, both days included
SeasonSchedule::yearly('America/Toronto', '2026-12-15', 23);                     // each year from the 15th of December
SeasonSchedule::yearly('America/Toronto', '2026-11-26', 4, 'FREQ=YEARLY;BYMONTH=11;BYDAY=4TH'); // the fourth Thursday of November
```

A yearly season starts on the dates its rule gives from its first date, which the rule starts from: `FREQ=YEARLY` alone
comes back on the same date each year, `INTERVAL=2` every other year, `COUNT` and `UNTIL` end it. A season lasts 1 to
183 days, half a year, so that one time of a season never meets its next. A season on the 29th of February comes back
on the next 29th of February.

Its days start at midnight on the wall clock of its timezone, across changes of time: a day of 23 or 25 hours is one
day. Each time a season comes is a `Period`, from the start of its first day, included, to the start of the day after
its last, excluded. Its `code` is the date of its first day, `2026-12-15`: it stays the same for that time whatever the
clock says, so that a quest done or an item earned in it is told apart from the next year.

| Method | Answers |
|---|---|
| `periodAt($moment)` | The time of the season a moment falls in, or null. |
| `nextPeriod($after)` | The first time that starts after a moment, or null when it never comes again. |
| `periods($from, $through, $limit)` | The times that start between two dates, at most so many. |

## Seasons

A `Season` has a stable identifier — the same from one year to the next —, its schedule, what it brings while it lasts,
and its events:

```php
new Season('winter-festival', $schedule, [
    Content::quest('quest:winter-hunt'),
    Content::item('item:snow-hat'),
    Content::decoration('decoration:snowflakes'),
], $events);
```

A `Content` is a kind — `ContentKind::Quest`, `Item` or `Decoration` — and a reference the application or the package
it belongs to knows. A season brings at most 200 contents and holds at most 50 events.

## Events

A `SeasonalEvent` runs inside a season, from one of its days for a number of days — to the season's end by default —
and adds its own contents while it runs:

```php
new SeasonalEvent('gift-week', fromDay: 8, days: 7, contents: [Content::item('item:wrapped-die')], multiplier: new Multiplier('points', 200), announceDays: 7);
```

An event starts and ends at midnight in the season's timezone, and never runs past the season's end. Its identifier is
unique in its season.

## Announcements and multipliers

An event is announced `announceDays` days before it starts, 0 to 90: from then, `SeasonalEvents::announced()` lists it,
so that a site shows what is coming. A `Multiplier` multiplies the gains of a ledger while its event runs, in percent:
150 adds half, 200 doubles, 101 to 500. An event with a multiplier is announced at least one day ahead: the members know
of it before it begins, and nothing multiplies by surprise. Multipliers of events that meet never add up: the highest
applies. `Multiplier::apply()` rounds down, so that a gain never grows by a fraction of a point.

## Limits

Every value is checked when it is built, and a refusal throws `InvalidSeason` with the reason. Identifiers are 1 to 64
lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit.
