# tilttek/quests

**Quests and challenges with steps, read on what members do: for one member, a team, the whole community, or two members racing each other.**

tilttek/quests turns the members' activity, recorded by `tilttek/activity`, into quests accomplished. A quest is a
list of steps — *play three games*, *read 300 pages of a rule book*, *publish a first sheet* — done in order or in any
order. It is open always, between two dates in a declared timezone, or at dates that come back, and is accomplished
once per occurrence. Quests chain: accomplishing one opens the next. A member follows a quest as soon as it is open or
once they accept it, and may abandon it. Besides the personal quest, three genres are built in: the challenge of the
community, a collective goal everyone's activity advances — *a thousand games this month* —; the team quest, joined
under a team towards a goal its members share; and the duel, a challenge one member sends another, won by the first to
accomplish it. Rewards are declared, never executed: an accomplished quest tells the application its rewards — points,
a badge, an item — and the members they go to, and the application gives them through the packages it has. Everything
is replayed from the journal when a rule changes, and erasing a member removes what the quests kept of them.

It is for an application that gives its members goals to reach — a community site, a game site, a reading or writing
platform, a club. `tilttek/quests-bundle` integrates it with Symfony, keeps the quests and their progress in Doctrine
DBAL and computes seasonal dates with `phil/almanac`; an application without Symfony takes the library alone and
writes its own stores, or uses those in memory.

It is a plain PHP library whose dependencies are `tilttek/activity`, the journal its steps read, `phil/resource`, the
opaque reference of a member or a team, and `psr/clock`. Members are references the application gives, `member:44`:
the library never reads a name, an e-mail or an account. It depends on no other gamification package: a site that
wants only quests installs nothing else.

The design is small: a `Quest` holds its `Step`s, its `Schedule`, its `Reward`s and the quest it follows; a `Catalog`
holds the quests; `QuestProjection` reads the journal, writes `Contribution`s and `Completion`s and decides duels;
`Quests` accepts, abandons and reads where each quest stands; `Duels` sends and answers challenges; `Evaluator` and
`ProgressCalculator` read a subject's progress from its contributions.

## Status

| Part | State |
|---|---|
| `Scope`, `Step`, `StepMeasure`, `Reward`, `Quest`, `QuestKind`, `Admission`: steps counted or totalled, ordered or free, rewards declared, genres and admission | Implemented and tested |
| `Schedule`, `Permanent`, `Dated`, `Occurrence`, `ScheduleReader`, `Window`: quests open always, between two dates, or at dates a reader of its own computes | Implemented and tested |
| `QuestReader`, `Catalog`, `InMemoryCatalog`, `WritableCatalog`, `Chains`: quests from plain values, their chains checked | Implemented and tested |
| `Contribution`, `Enrolment`, `Completion`, and their four stores with the in-memory implementations and the contracts any store passes | Implemented and tested |
| `ProgressCalculator`, `QuestProgress`, `StepProgress`, `Finish`, `Evaluator`: a subject's progress over an occurrence | Implemented and tested |
| `QuestProjection`: the activity turned into progress, quests accomplished, chains opened, duels decided, cancellations and rebuilds | Implemented and tested |
| `Quests`: acceptance, abandonment, status, board, journal of accomplished quests, export and erasure | Implemented and tested |
| `Duel`, `DuelStatus`, `Duels`, `DuelStanding`: challenges sent, accepted, declined, withdrawn, won and expired | Implemented and tested |
| `QuestCompleted`, `QuestObserver`: what is told, with the rewards and their members | Implemented and tested |
| Exceptions under one `QuestsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Quests and steps](doc/quests-and-steps.md) | Steps, ordered steps, rewards, the definition a configuration holds. |
| [Schedules and chains](doc/schedules-and-chains.md) | When a quest is open, its occurrences, and the quests that follow others. |
| [Following a quest](doc/following.md) | Automatic or accepted, abandonment, where a quest stands for a member. |
| [Genres](doc/genres.md) | Personal quests, challenges of the community, team quests and duels. |
| [The projection](doc/projection.md) | How the journal is read, what is told, cancellations and rebuilds. |
| [Erasure](doc/erasure.md) | What exporting and erasing a member covers. |
| [Writing a store](doc/writing-a-store.md) | The four store contracts an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the catalog, stores, schedules, the observer, the clock. |
| [Exceptions](doc/exceptions.md) | `QuestsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that reads the quests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/quests
```

```php
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\QuestReader;

$catalog = InMemoryCatalog::fromArray([
    'first-steps' => [
        'ordered' => true,
        'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 3], ['code' => 'write', 'verbs' => ['sheet.published']]],
        'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]],
    ],
    'thousand-games' => ['kind' => 'community', 'opens' => '2026-12-01', 'closes' => '2027-01-01', 'steps' => [['verbs' => ['game.played'], 'count' => 1000]]],
], new QuestReader('America/Toronto'));

// Wire QuestProjection to the journal, then read:
$quests->status($member, 'first-steps')->progress->done(); // 1
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
