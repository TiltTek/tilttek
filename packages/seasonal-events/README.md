# tilttek/seasonal-events

**Seasons and their events: the quests, items and decorations of a period, in the site's timezone, coming back each
year.**

tilttek/seasonal-events keeps the seasons of a site — a winter festival, a spooky season, a harvest feast — and the
events inside them. A season comes once, between two dates, or each year for a number of days from a date an RFC 5545
yearly rule gives: the 15th of December, the fourth Thursday of November. Its days are counted in its timezone, from
midnight on the wall clock, across changes of time. A season brings quests, items and decorations while it lasts; an
event inside it adds its own for some of its days, and may multiply the gains of a ledger — only when it is announced at
least a day ahead, so that nothing multiplies by surprise and the highest multiplier applies when events meet. Each time
a season comes has a code, the date of its first day, so that what a member earns in it is told apart from the next
year.

The other packages read what is on through one port, `ActiveContent`: the quests open now, the items offered, the
decorations shown, the multiplier of a ledger. They never read the seasons themselves, and the library depends on none
of them: optional bridges open a `tilttek/quests` quest with a season and list the `tilttek/collections` items it
brings, when those packages are installed.

It is for a community or a game that wants seasonal content without hand-switching it on and off: the seasons are
declared ahead, in code, a configuration or a database, and answer for any moment. There is no season pass to buy and
no item that never comes back: a season that comes each year brings its items again.

It is a plain PHP library whose dependencies are `phil/recurrence`, which reads the yearly rules, `phil/resource`, the
references of quests, items and decorations, and `psr/clock`. It keeps no state between two questions.

## Status

| Part | State |
|---|---|
| `SeasonSchedule`: once, or each year by an RFC 5545 yearly rule, in a timezone, up to 183 days | Implemented and tested |
| `Season`, `SeasonalEvent`, `Content`, `ContentKind`, `Multiplier`: what a season and its events bring | Implemented and tested |
| `SeasonalEvents`, `Snapshot`, `ActiveSeason`, `ActiveEvent`, `Period`: what is on at a moment, and what is announced | Implemented and tested |
| `ActiveContent`: the port other packages read | Implemented and tested |
| `SeasonCatalogue`, `InMemorySeasonCatalogue`, `SeasonReader`: seasons from code or plain arrays, and back | Implemented and tested |
| Bridges to `tilttek/quests` and `tilttek/collections` | Implemented and tested |
| Exceptions under one `SeasonalEventsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring the library. |
| [Seasons and events](doc/seasons-and-events.md) | Schedules, timezones, recurrence, contents, events, announcements and multipliers. |
| [What is on](doc/what-is-on.md) | The questions of a moment, the snapshot, the announced events and the port `ActiveContent`. |
| [Definitions](doc/definitions.md) | Seasons written as plain arrays — a configuration, JSON or YAML — read and written back. |
| [Bridges](doc/bridges.md) | Quests, collections, and an advent calendar during a season. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the catalogue, the clock. |
| [Testing](doc/testing.md) | The library's tests, fuzzing and benchmarks, and how to test code that reads the seasons. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/seasonal-events
```

```php
use Symfony\Component\Clock\Clock;
use Tilttek\SeasonalEvents\{Content, Multiplier, Season, SeasonalEvent, SeasonalEvents, SeasonSchedule};
use Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue;

$winter = new Season(
    'winter-festival',
    SeasonSchedule::yearly('America/Toronto', '2026-12-15', 23),
    [Content::quest('quest:winter-hunt'), Content::decoration('decoration:snowflakes')],
    [new SeasonalEvent('gift-week', fromDay: 8, days: 7, contents: [Content::item('item:wrapped-die')], multiplier: new Multiplier('points', 200), announceDays: 7)],
);

$seasons = new SeasonalEvents(new InMemorySeasonCatalogue([$winter]), new Clock());

$seasons->at()->decorations();          // [decoration:snowflakes] while the festival is on
$seasons->multiplier('points')?->apply(10); // 20 during the gift week
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
