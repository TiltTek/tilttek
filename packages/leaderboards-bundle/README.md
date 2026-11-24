# tilttek/leaderboards-bundle

**Leaderboards for a Symfony application, kept up to date from its members' activity, each member shown only by their
choice.**

tilttek/leaderboards-bundle integrates `tilttek/leaderboards` with Symfony. The boards — their measures, verbs and
context conditions, periods and timezones, seasons and sliding windows, ties and tie-breaks, snapshot sizes, leagues,
rankings by object, titles and the luck of teams — are written in the configuration and checked when the container is
compiled. The contributions, the scores, the snapshots and the members' choices of appearance, the tiers and divisions
of the leagues and the leads taken are kept in Doctrine DBAL, in nine tables the bundle adds to the schema the ORM generates, so that the
application's migrations create them; the scores may live in the sorted sets of Valkey instead, connected by Symfony
Cache or by the application's own client. The projection that keeps the scores up to date is tagged for `tilttek/activity-bundle`, which follows the
journal at each gesture, catches it up and rebuilds it. A snapshot taken, a week of a league settled and a member who reached
the top — first once the period closed, or in the lead as their score changed, as the site chooses — become events of
the application's dispatcher, to reward the first and congratulate those who went up, each carrying how the member
chose to appear, and the screen the member opens next celebrates the top with the PHIL UX kit's accessible
`Celebration`; Twig functions and templates show
a standing, a snapshot, a member's division or their league screen as one member sees it, and a ranking of teams — the
league screen and the teams built of the recipes of a UX Toolkit kit —; commands take the snapshots due, settle the
weeks due, slide the windows and show a board as the public sees it, and Symfony Scheduler can run the first three; and
a member's scores, lines, choice, tiers, divisions and leads are exported and erased with their account through
`phil/privacy-bundle`.

It is for a Symfony application that ranks its members on what they do and must not expose those who did not choose
to be shown. The library alone suits an application without Symfony.

It requires `tilttek/leaderboards`, Doctrine DBAL, the PSR event dispatcher and Symfony's configuration and container;
the ORM, the console, Twig, Twig components, the translator, Symfony Scheduler, Symfony Cache and `phil/privacy-bundle`
are optional, and the bundle registers what uses
them only when they are installed.

The design follows the library: the stores implement its four contracts, `LeaderboardsServices` wires the services,
`DispatchingObserver` turns a snapshot and a settlement into the `SnapshotTaken`, `LeagueSettled` and `TopReached`
events, `LeadWatch` watches the scores kept for the lead when the site celebrates on a score change,
`LeaderboardsExtension` is the Twig side, `LeaderboardsSchedule` the scheduled side.

## Status

| Part | State |
|---|---|
| Configuration of the boards, timezones and stores, checked at compile time | Implemented and tested |
| Tie-breaks, sliding windows, rankings by object, titles and the luck of teams in the configuration | Implemented and tested |
| The scores in Valkey through Symfony Cache's connection or the application's own client | Implemented and tested |
| `DbalScoreStore`, `DbalSnapshotStore`, `DbalAppearanceStore`, `DbalLeagueStore`, `DbalLeadStore` and the nine tables, with the schema listener, on SQLite, MySQL and PostgreSQL | Implemented and tested |
| The projection tagged for `tilttek/activity-bundle` | Implemented and tested |
| The weekly leagues of the boards that declare one | Implemented and tested |
| The `SnapshotTaken`, `LeagueSettled` and `TopReached` events | Implemented and tested |
| The celebration of the top, on the kit's recipe `Celebration`, at the close of the period or on a score change | Implemented and tested |
| Twig functions and the standing and division templates, in English and French | Implemented and tested |
| The league screen and the ranking of teams, built of the kit's recipes `Card`, `Badge` and `Empty` | Implemented and tested |
| Commands `tilttek:leaderboards:snapshot`, `tilttek:leaderboards:settle`, `tilttek:leaderboards:slide` and `tilttek:leaderboards:show` | Implemented and tested |
| The schedule `tilttek_leaderboards` on Symfony Scheduler | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables, the projection and the scheduled snapshots. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Database](doc/database.md) | The nine tables, their keys, the insertion written once, and the scores in Valkey. |
| [Events](doc/events.md) | The events a snapshot, a settled week and a top reached dispatch, and how to reward the first. |
| [Templates](doc/templates.md) | The Twig functions, the standing, division, league, teams and celebration templates, the kit's recipes, the names of named members and the translations. |
| [Commands](doc/commands.md) | Taking the snapshots due, settling the weeks due, sliding the windows, and showing a board. |
| [Scheduling](doc/scheduling.md) | The snapshots, settlements and windows due run from Symfony Scheduler or a cron. |
| [Privacy](doc/privacy.md) | What a member's export holds and what their erasure removes. |
| [Extension points](doc/extension-points.md) | Replacing a store, the Valkey client, the boards, the observer, the screens. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\LeaderboardsBundle\TilttekLeaderboardsBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_leaderboards.yaml
tilttek_leaderboards:
    timezone: America/Toronto
    boards:
        games-month: { measure: count, verbs: ['game.played'], period: month }
        level: { measure: latest }
        weekly-xp: { measure: total, verbs: ['lesson.done'], period: week, league: { tiers: [bronze, silver, gold] } }
    schedule: true
```

```twig
{{ include('@TilttekLeaderboards/standing.html.twig', {standing: tilttek_leaderboards_standing('games-month', 'member:44'), names: names}) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.6.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
