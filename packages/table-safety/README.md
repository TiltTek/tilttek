# tilttek/table-safety

**The safety tools of a role-playing table: what it agrees before play, the signals that stop or change a scene during
play, and what it enjoyed after — anonymous by default.**

tilttek/table-safety holds the answers and the signals of the safety tools the tabletop and live-action role-playing
communities use, around a table or online; the application draws their screens. Before play, each participant gives
their lines and veils and their consent checklist — green, yellow or red — on the themes of `phil/content-warnings`,
the same list that warns of a game's content, plus the themes the table adds; they answer the session zero their game
master composed from a built-in template. During play, anyone raises the X-card, a pause, a rewind or a fast-forward of
Script Change, or leaves through the open door, without explaining why; the game master asks for a check-in, and each
answers with a gesture. After play, each gives their stars and wishes for the session.

The summaries never say who gave which answer: a table sees, for each theme, the most restrictive answer anyone gave,
the tallies of its session zero, its stars and wishes in alphabetical order. A signal is anonymous unless the table
chose named signals, and nothing — stored or pushed — keeps the author of an anonymous signal. The answers are
sensitive, so they are kept for their table only, and a participant's are exported and erased with their account.

It is a plain PHP library, for any application that runs tables: a club, a convention, a play-by-post site, a virtual
tabletop. In a Symfony application, reach for `tilttek/table-safety-bundle`, which adds the Doctrine storage, the forms,
the screens of the table and of the game master, and the signals in real time through Mercure.

The tools come from their communities; the library describes them in its own words and credits their authors in
[Credits](doc/credits.md).

## Status

| Part | State |
|---|---|
| `LinesAndVeils`, `ConsentChecklist` and the themes of a table, on `phil/content-warnings` | Implemented and tested |
| `TableAgreement`: the strictest answer of each theme, and the warning preferences it stands for | Implemented and tested |
| Session zero: `Question`, `Template`, the four built-in `Templates`, `Answers` and the `Pact` | Implemented and tested |
| Signals: the X-card, Script Change, the open door and check-ins, anonymous by default, seen by the table or the game master alone | Implemented and tested |
| `StarsAndWishes` and the `FeedbackSummary` of a session | Implemented and tested |
| `TableSettings`, the four store contracts and their memory stores | Implemented and tested |
| `TableSafety`: the service of all of the above, and a participant's export and erasure | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Before play](doc/before-play.md) | Lines and veils, the consent checklist, the table's themes and what the table agreed. |
| [Session zero](doc/session-zero.md) | Questions, the built-in templates, a game master's own questions, answers and the pact. |
| [During play](doc/during-play.md) | The signals, who sees them, named signals and check-ins. |
| [After play](doc/after-play.md) | Stars and wishes, and the summary of a session. |
| [Settings](doc/settings.md) | What a table chooses, and its defaults. |
| [Privacy](doc/privacy.md) | What stays anonymous, what is kept, and a participant's export and erasure. |
| [Writing a store](doc/writing-a-store.md) | The four contracts and what each implementation must do. |
| [Credits](doc/credits.md) | The tools, the people and communities they come from, and their licences. |
| [Extension points](doc/extension-points.md) | What an application replaces or adds. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/table-safety
```

```php
use Phil\ContentWarnings\BaseCatalogue;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\Store\InMemoryFeedbackStore;
use Tilttek\TableSafety\Store\InMemoryParticipationStore;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\Store\InMemoryTableStore;
use Tilttek\TableSafety\TableSafety;

$safety = new TableSafety(BaseCatalogue::load(), new InMemoryTableStore(), new InMemoryParticipationStore(), new InMemorySignalStore(), new InMemoryFeedbackStore());
$table = ResourceReference::of('table', '7');
$now = new DateTimeImmutable();

$safety->answerLinesAndVeils($table, ResourceReference::of('member', '12'), LinesAndVeils::none()->with('spiders', Boundary::Line), $now);
$safety->agreement($table)->lines();           // ['spiders'], without saying whose line it is

$signal = $safety->raise($table, ResourceReference::of('member', '12'), SignalKind::XCard, false, $now);
$signal->isAnonymous();                          // true
```

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-sodium`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
