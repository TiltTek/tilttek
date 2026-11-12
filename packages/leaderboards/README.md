# tilttek/leaderboards

**Members ranked on what they do, by period, and nobody shown to the others without choosing it.**

tilttek/leaderboards ranks members on a measure during a period: the games played this month, the points earned this
week, the best score of a single game, the days of activity, a level or the longest streak. Four measures are kept
from the members' activity, recorded by `tilttek/activity` — the gestures counted, their quantities added up, the best
quantity of one gesture, the distinct days with a gesture —; two take a value the application reports from a balance it
keeps elsewhere — the latest value, or the highest. A board ranks over a day, an ISO week, a month, a quarter, a year,
named seasons, ever or a sliding window of its last days, each in the timezone it declares. Scores are kept up to date
as activity is recorded, not counted again at each display; a cancellation takes its contribution back, and a changed
board is replayed from the journal. Equal scores are ordered by up to two tie-breaks — the fewest attempts, the
shortest time —, then go to the first who reached them, or share a rank. A board is ranked among friends or inside a
group by a circle of members the application gives, and may rank members for each object — each game, each book. A
snapshot freezes the first members at the end of a period, once, to show the final standing and reward them. A weekly
board may have a league: its members are split into divisions of about thirty, in tiers, formed in their order of
arrival or by their volume of the week before; each sees only their division, and at the end of the week the first go
up a tier and the last go down. A board may give a title from a threshold, and rank teams with a share of luck the
application enables. Nobody is ranked publicly without choosing it: a member who has not chosen to appear sees their
own rank, and the others see a line without a name; a member may appear under their name or a pseudonym. Erasing a
member removes their scores, their lines in the snapshots and their choice.

It is for an application that wants its members to compare what they do — a community site, a game site, a reading or
writing platform —, and that must not expose a member who did not ask to be. `tilttek/leaderboards-bundle` integrates
it with Symfony and keeps the boards in Doctrine DBAL, the scores in Valkey when it is enabled; an application without
Symfony takes the library alone, with the stores in memory, the scores in Valkey through the client it has, or stores
of its own.

It is a plain PHP library whose dependencies are `tilttek/activity`, the journal the boards read, `phil/resource`, the
opaque reference of a member, and `psr/clock`. Members are references the application gives, `member:44`: the library
never reads a name, an e-mail or an account, and the name a public line shows is the application's to give.

The design is small: a `Board` holds its `Measure`, its `Schedule` of `Period`s, its verbs and conditions, its `Ranking`
of `TieBreak`s and `Ties`, and its options; `LeaderboardProjection` reads the journal and writes `Contribution`s and
`Score`s to a `ScoreStore`; `Leaderboards` reads a `Standing` as one viewer sees it — `Row`s by each member's
`Appearance` —, takes the values the application reports, takes `Snapshot`s, and exports and erases a member; `Leagues`
places members in the divisions of a board's `League` and settles its weeks.

## Status

| Part | State |
|---|---|
| `Board`, `Boards`, `Measure`, `Ties`: boards on six measures, from a configuration | Implemented and tested |
| `TieBreak`, `Aggregate`, `Direction`, `Ranking`: up to two tie-breaks, then the first to reach a score or a shared rank | Implemented and tested |
| `Period`, `PeriodKind`, `Schedule`: days, ISO weeks, months, quarters, years, seasons, ever and sliding windows, in a timezone | Implemented and tested |
| `LeaderboardProjection`: scores kept up to date from the journal, cancellations, rebuilds and sliding windows | Implemented and tested |
| `Leaderboards`: standings, ranks, circles, rankings by object, reported values, appearances, snapshots, export and erasure | Implemented and tested |
| `Title`, `Team`, `TeamRow`, `Chance`: titles from a threshold; teams ranked, with the luck a board enables | Implemented and tested |
| `ValkeyScoreStore`, `ValkeyConnection`, `RawCommandConnection`: the scores in the sorted sets of Valkey | Implemented and tested |
| `Appearance`, `Visibility`, `Row`, `Standing`: hidden by default, by name or by pseudonym | Implemented and tested |
| `Snapshot`, `Placing`, `LeaderboardObserver`: the final standing of a period, told once | Implemented and tested |
| `League`, `Leagues`, `Division`, `Settlement`, `Formation`: weekly divisions in tiers, formed by arrival or by volume, promotion and relegation | Implemented and tested |
| Four stores and their in-memory implementations, with the contracts any store passes | Implemented and tested |
| Exceptions under one `LeaderboardsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Boards and periods](doc/boards-and-periods.md) | Measures, verbs, conditions, periods, timezones, seasons, sliding windows, ties and tie-breaks, rankings by object and the configuration of boards. |
| [Scores and the projection](doc/scores-and-projection.md) | How the journal is read, cancellations, rebuilds, and the values the application reports. |
| [Standings](doc/standings.md) | A board as one viewer sees it, ties, the viewer's own line and circles. |
| [Appearing on a board](doc/appearance.md) | Hidden by default, by name or by pseudonym, and what each viewer sees. |
| [Snapshots](doc/snapshots.md) | Freezing a period, rewarding the first, and showing the final standing. |
| [Leagues](doc/leagues.md) | Weekly divisions in tiers, entering a division, divisions formed by volume, promotion and relegation at the end of the week. |
| [Teams and titles](doc/teams-and-titles.md) | Titles held from a threshold, teams ranked, and the luck a board adds to their scores. |
| [Erasure](doc/erasure.md) | What a member's export holds and what erasing them removes. |
| [Writing a store](doc/writing-a-store.md) | The four store contracts an application's storage implements, and the scores in Valkey. |
| [Extension points](doc/extension-points.md) | What an application gives the library: stores, observers, the clock. |
| [Exceptions](doc/exceptions.md) | `LeaderboardsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that reads the boards. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Leaderboards\{Appearance, Boards, LeaderboardProjection, Leaderboards};
use Tilttek\Leaderboards\Store\{InMemoryAppearanceStore, InMemoryScoreStore, InMemorySnapshotStore};

$boards = Boards::fromArray([
    'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'month'],
    'level' => ['measure' => 'latest'],
], 'America/Toronto');
$scores = new InMemoryScoreStore();
$projection = new LeaderboardProjection($boards, $scores); // handed to the journal's projections
$leaderboards = new Leaderboards($boards, $scores, new InMemorySnapshotStore(), new InMemoryAppearanceStore(), $clock);

$leaderboards->appear($member, Appearance::pseudonym('Le Nain Gris'));
$standing = $leaderboards->standing('games-month', viewer: $member);
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
