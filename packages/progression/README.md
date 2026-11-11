# tilttek/progression

**Experience that adds up into levels, seasons that start a track again, and streaks counted in each member's own day.**

tilttek/progression turns the members' activity, recorded by `tilttek/activity`, into experience and streaks.
Experience is earned by rules — *fifty per game run as game master, two hundred at most a day* — into an account that
is never spent, and a curve says the experience each level starts at: linear, quadratic, exponential, or a table the
application writes. A site keeps as many tracks as it needs — player, game master, author —, each going to the member
or to the gesture's object, such as a character whose experience follows the rules of its game. A track may start
again at each season — every month, quarter or year, or named periods such as a summer event — and keeps what was
reached in the seasons past; milestones of experience or level are told once, for the application to reward. A streak
counts the days or weeks in a row a member makes a gesture, read in the member's timezone, so that midnight in
Montréal does not break a streak in Paris; a grace forgives a few missed days, a frozen day keeps the run alive, and
the best run is kept beside the current one. Everything is replayed from the journal when a rule changes, and erasing
a member removes what they earned.

It is for an application that rewards what its members do and wants them to see how far they have come — a community
site, a game site, a reading or writing platform. `tilttek/progression-bundle` integrates it with Symfony and keeps
the progression in Doctrine DBAL; an application without Symfony takes the library alone and writes its own stores, or
uses those in memory.

It is a plain PHP library whose dependencies are `tilttek/activity`, the journal its rules and streaks read,
`phil/resource`, the opaque reference of a member or an object, and `psr/clock`. Members are references the
application gives, `member:44`: the library never reads a name, an e-mail or an account.

The design is small: a `Track` holds a `Curve`, its `ExperienceRule`s, its `SeasonSchedule` and its `Milestone`s;
`ProgressionProjection` reads the journal and writes `Gain`s, milestones and streak gestures to three stores;
`Progression` reads standings, seasons past, milestones and streaks, freezes days, sweeps the broken streaks and
erases a member; `StreakKeeper` and `StreakCalculator` count the runs.

## Status

| Part | State |
|---|---|
| `Curve` and `Level`: linear, quadratic, exponential and table curves, where experience stands | Implemented and tested |
| `ExperienceRule`, `Cap`, `Period`, `Gain`: experience per gesture, caps per subject and period | Implemented and tested |
| `Track`, `Tracks`, `Subject`, `Milestone`: tracks of members or objects, from a configuration | Implemented and tested |
| `Season`, `CalendarSeasons`, `NamedSeasons`: seasons by month, quarter or year, or named | Implemented and tested |
| `StreakRule`, `StreakRules`, `StreakUnit`, `Grace`, `StreakCalculator`, `StreakKeeper`: streaks of days or weeks, grace, frozen units, the sweep | Implemented and tested |
| `TimezoneResolver`, `FixedTimezone`, `ContextTimezone`: the member's timezone | Implemented and tested |
| `ProgressionProjection`: the activity turned into experience and streaks, level changes, milestones, cancellations and rebuilds | Implemented and tested |
| `Progression`: standings, seasons past, milestones, streaks, freezes, sweep, erasure | Implemented and tested |
| Three stores and their in-memory implementations, with the contracts any store passes | Implemented and tested |
| Exceptions under one `ProgressionException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Tracks and levels](doc/tracks-and-levels.md) | Curves, rules, caps, subjects, standings and the configuration of tracks. |
| [Seasons](doc/seasons.md) | Calendar and named seasons, and the seasons past. |
| [Milestones](doc/milestones.md) | Steps of experience or level worth a reward, told once. |
| [Streaks](doc/streaks.md) | Days or weeks in a row, the member's timezone, grace, frozen days and the sweep. |
| [The projection](doc/projection.md) | How the journal is read, what is told, cancellations and rebuilds. |
| [Erasure](doc/erasure.md) | What erasing a member removes. |
| [Writing a store](doc/writing-a-store.md) | The three store contracts an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: stores, seasons, timezones, the observer, the clock. |
| [Exceptions](doc/exceptions.md) | `ProgressionException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that reads the progression. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/progression
```

```php
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Tracks;

$tracks = Tracks::fromArray([
    'player' => [
        'curve' => ['type' => 'quadratic', 'base' => 50, 'levels' => 60],
        'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day']]],
        'milestones' => ['veteran' => ['level' => 10]],
    ],
], 'America/Toronto');
$streaks = StreakRules::fromArray(['daily-play' => ['verbs' => ['game.played'], 'grace' => ['allowed' => 1, 'window' => 7]]]);

// Wire ProgressionProjection to the journal, then read:
$progression->standing($member, 'player')->level->number; // 3
$progression->streak($member, 'daily-play')->current;     // 5
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
