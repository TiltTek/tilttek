# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/activity`, the journal of the members' activity that the rules and the streaks read.
- `phil/resource`, the opaque reference of a member or an object (`member:44`, `character:9`).
- `psr/clock`, the interface of the clock; any PSR-20 clock does, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/progression
```

Nothing is registered and nothing is configured: build the stores, `StreakKeeper`, `ProgressionProjection` and
`Progression` where you use them. A Symfony application installs `tilttek/progression-bundle`, which keeps the
progression in Doctrine DBAL and builds everything from its configuration.

```php
use Tilttek\Progression\Progression;
use Tilttek\Progression\ProgressionProjection;
use Tilttek\Progression\Store\InMemoryExperienceStore;
use Tilttek\Progression\Store\InMemoryMilestoneStore;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Streak\StreakKeeper;

$experience = new InMemoryExperienceStore();
$milestones = new InMemoryMilestoneStore();
$streakStore = new InMemoryStreakStore();
$keeper = new StreakKeeper($streaks, $streakStore, $observer);
$projection = new ProgressionProjection($tracks, $keeper, $experience, $milestones, $clock, observer: $observer);
$progression = new Progression($tracks, $keeper, $experience, $milestones, $streakStore, $clock);
```

Hand the projection to `tilttek/activity`'s `LiveProjections` or `Projector`, so that it reads the journal. The
stores in memory forget everything with the process: they are for tests, scripts and examples. An application that
keeps its progression writes stores on its storage ([Writing a store](writing-a-store.md)) or installs the bundle.
