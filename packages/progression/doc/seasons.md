# Seasons

A season is a named period at the end of which a track starts again from zero. A track without seasons counts all of
its experience together.

## Calendar seasons

`new CalendarSeasons(SeasonLength::Month, $timezone)` follows the calendar without a gap: every month (`2026-11`),
every quarter (`2026-q4`) or every year (`2026`), read in the timezone of `Tracks` when built from a configuration
(`'seasons' => ['length' => 'quarter']`).

## Named seasons

`new NamedSeasons(new Season('summer-2026', $start, $end), …)` names and dates seasons — an event, a summer. A season
starts at `start`, included, and ends at `end`, excluded; two seasons never overlap, and time may be left between them:
an activity between two seasons brings experience to no season, which still counts in the lifetime.

```php
'seasons' => ['named' => [
    ['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22'],
]],
```

## The seasons past

Each gain keeps the code of the season its activity fell in, so a season starts at zero without any task running at
its end. `$progression->history($subject, 'player')` returns a `SeasonResult` — the `Season` and the `Level` reached —
for each season that ended, the oldest first. The season under way is left out, and so is a season the track no longer
has: a named season removed from the configuration leaves the history.
