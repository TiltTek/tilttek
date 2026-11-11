# Streaks

A `StreakRule` counts the days — or ISO weeks, with `StreakUnit::Week` — in a row a member makes one of the gestures
it reads, with the facts the activity's context must hold.

```php
new StreakRule('daily-play', ['game.played'], StreakUnit::Day, new Grace(1, 7));
```

## The member's day

A day is the member's, not the server's. A `TimezoneResolver` says the timezone of each activity: `FixedTimezone` for
a site whose members share one, `ContextTimezone('timezone', $fallback)` to read it from the activity's context, which
the application fills when it records the gesture, from the member's profile on the server, never from a value the
client sends: a client that picks the timezone of each gesture can place it in a 26-hour range and keep a daily streak
alive while acting every second day. Kept with the activity, the timezone stays the same in a rebuild,
even after the member moves. The state of a streak remembers the timezone of the member's last gesture, which the
sweep and the reads use.

## Runs

`StreakCalculator` walks the units from the member's first active one. A run goes on through each active unit, which
adds one; through each frozen unit and each missed unit the grace forgives, which add nothing; and breaks on any other
missed unit. The current unit is not over: missing it breaks nothing yet. The units where nothing can change — before
a run starts again, and from the current unit on — are skipped, so the work grows with the active, frozen and forgiven
units, not with the span of time between them. `StreakStatus` gives the `current` run — 0
once broken —, the `best` run ever, the unit the run started and the last active one, and the `graceLeft`.

## Grace

`new Grace(allowed, window)` forgives `allowed` missed units in any `window` units in a row: *one day a week* is
`new Grace(1, 7)`. A forgiven day keeps the run alive without adding to it.

## Frozen days

`$progression->freeze($member, 'daily-play', $day)` freezes the member's day holding the moment, read in their
timezone: a frozen day missed keeps the run alive without adding to it. The application decides who may freeze and
what it costs — a reward bought with points, a few a month — and `thaw()` undoes it, for a refund.

## A streak broken

A streak breaks once, and `ProgressionObserver::streakBroken()` is told: by the member's next gesture, when it finds
the run they were on over, or by `$progression->sweep()`, which the application runs once an hour — members live across
timezones — and which looks only at the streaks still alive whose last active unit is past. A cancellation recounts a
streak silently.
