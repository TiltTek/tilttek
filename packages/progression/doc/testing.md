# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the curves and their refusals, the rules, caps and periods across timezones, the seasons, the tracks
from a configuration, the runs of a streak — grace, frozen units, gaps, gestures ahead of now —, the keeper and its
sweep in the members' timezones, the stores in memory through `ExperienceStoreTestCase`, `MilestoneStoreTestCase` and
`StreakStoreTestCase` — the contracts any store passes —, the projection with the journal of `tilttek/activity`:
levels, milestones, caps, objects, seasons, cancellations and rebuilds, and `Progression`. In the monorepo, Infection
holds the library at 100 % of covered mutants.

## Testing code that reads the progression

Build the stores in memory, a `Journal` over an `InMemoryActivityLog` with `LiveProjections` around a
`ProgressionProjection`, and a clock you control; record activities, then read:

```php
$journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 20:00')));

self::assertSame(1, $progression->streak($member, 'daily-play')->current);
```

An observer that writes down what it is told checks the level ups, the milestones and the broken streaks.
