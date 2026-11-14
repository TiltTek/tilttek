# tilttek/rating

**Per-game ratings of players from their results: Elo with a decreasing K, OpenSkill with Plackett-Luce or
Thurstone-Mosteller and a conservative ranking, private by default and public with the member's consent.**

A rating measures how strong a member is at one game compared with the members they played: it rises with a win
against a stronger side and falls with a loss against a weaker one. Each game has its own ratings, in one rating
system:

- **Elo**, for duels first: a configurable K-factor, fixed or decreasing as a member plays and climbs; games of
  several players and team games read by pairs of sides; draws.
- **OpenSkill**, the Bayesian approximation of Weng and Lin, for any number of players or teams: a skill and its
  uncertainty, the Plackett-Luce or the Thurstone-Mosteller model, and standings ordered by the conservative estimate
  `mu − 3 × sigma`, so that a newcomer climbs as the uncertainty shrinks.

A rating is **private by default**: only the member sees it until they make it public, and only public ratings appear
in the standings; a rating after few games is marked **provisional**, and the standings may leave it out. Each result
is counted **once**: recording it again is refused. A **friendly** result is played outside the ratings and changes
nothing. Members are designated by stable, opaque identifiers, never by a name. The ratings are kept in whole numbers of
a fixed point, rounded once per game, so **replaying** the results of a game from its source gives the same ratings
every time — after a correction, a deleted result, or a change of system.

It is for an application that runs game nights, a club ladder or online tables and wants a level for each player, shown
with care. It is a plain PHP library: the store and the source of results are the application's; a rated result
dispatches events an application listens to, to show the change or play a celebration for a new highest rating.

## Status

| Part | State |
|---|---|
| `Ratings`: recording each result once, friendly results, provisional ratings, standings with or without them, publishing and hiding, replay, export and erasure | Implemented and tested |
| `GameResult`: duels, placings of several players, team games, draws, written and read as plain values | Implemented and tested |
| `Elo\EloSystem`, `Elo\KFactor`, `Elo\KFactorStep`: Elo with a fixed or decreasing K, a scale and a floor | Implemented and tested |
| `OpenSkill\OpenSkillSystem`: Plackett-Luce and Thurstone-Mosteller, the conservative estimate | Implemented and tested |
| `MemberRating`, `Visibility`, `RatingChange`: a member's rating as stored, private by default | Implemented and tested |
| `Contract\RatingStore`, `Store\InMemoryRatingStore`, `Contract\PlaySource` | Implemented and tested |
| `Event\RatingChanged`, `Event\PeakReached`: the moments of a celebration | Implemented and tested |
| Exceptions under one `RatingException` interface | Implemented and tested |
| A source of results reading `tilttek/play-log` | Not provided: `tilttek/play-log` is not written yet; an application implements `Contract\PlaySource` |
| Glicko-2 | Not provided |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Recording results](doc/recording-results.md) | Results, sides and ranks, each result recorded once, friendly results, the changes and the events. |
| [Elo](doc/elo.md) | The formula, the K-factor, games of several players and teams, the scale and the floor. |
| [OpenSkill](doc/openskill.md) | Skills and uncertainty, the two models, the settings and the conservative estimate. |
| [Privacy and standings](doc/privacy-and-standings.md) | Private by default, publishing, the standings, provisional ratings, export and erasure. |
| [Replaying](doc/replaying.md) | The source of results, determinism and switching systems. |
| [Celebrations](doc/celebrations.md) | The events of a rating change and a new highest rating, with the PHIL UX kit. |
| [Storing](doc/storing.md) | The store contract, versions and concurrent results. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `RatingException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Rating\Elo\{EloSystem, KFactor};
use Tilttek\Rating\{GameResult, Ratings};
use Tilttek\Rating\Store\InMemoryRatingStore;

$ratings = new Ratings(new InMemoryRatingStore(), new EloSystem(KFactor::chess()));

$ratings->record(GameResult::duel('play-81', 'chess', new DateTimeImmutable(), 'member-1042', 'member-1077'));
$ratings->rating('chess', 'member-1042')->value();     // 1520.0, seen by the member alone
$ratings->publish('chess', 'member-1042');             // on the member's own choice
$ratings->standings('chess');                          // the public ratings, highest first
```

## Requirements

PHP 8.5 or later and `psr/event-dispatcher`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
