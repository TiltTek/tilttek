# tilttek/tournament

**Run tournaments in Swiss rounds, pools or elimination, with fair pairings, players dropping out and tie-breakers
declared in order.**

tilttek/tournament runs a tournament from its first round to its podium. A tournament has participants, known by
opaque identifiers and never by name, and one to four stages: Swiss rounds paired by score groups, pools where everyone
meets everyone by the Berger tables, in one or two legs, and a single-elimination bracket with seeds and byes, which
ends the tournament. A Swiss stage or pools send their best on to the next stage; the bracket of a second stage is
prepared before its qualifiers are known, its places showing the rank and pool that will fill them.

Each round is prepared with the reasons of its pairings — the bye and why, who plays first and why, the rematch
avoided, the participant floated to another score group — for an organiser to read and show before publishing it, or
to seat otherwise. The round number is kept apart from its date, so that a round moved to another day keeps its place.
A result is entered once by table: the same result entered again by the opponent changes nothing, and a contrary one
waits for an organiser to settle it. A result of a past round is corrected with the standings computed again and the
difference returned. A participant who withdraws is not paired again, and their tables left without a result are
forfeits. The standings break ties with the tie-breakers the tournament declares, in order — Buchholz and cut
Buchholz, Sonneborn-Berger, head-to-head, wins, mission points —, then with the draw of a published seed, never with a
chance that cannot be replayed. When the tournament has its podium, the library returns a `TournamentFinished` event,
the moment for a celebration.

It is for an application that runs tournaments for a game store, a convention or a club: chess, card games, board
games, miniatures games with their mission points. It is a plain PHP library without a dependency; the strengths of a
first order come through a port, and `Seeding\RatingSeeding` reads them from `tilttek/rating` when it is installed.

## Status

| Part | State |
|---|---|
| `Tournament`, `Stage`, `Scoring`, `Round`, `Pairing`, `Result`: a tournament declared, its stages, its scoring in whole numbers, its rounds and tables, its results with mission points | Implemented and tested |
| `Tournaments`: start, prepare and publish rounds, enter, settle and correct results, withdraw, reschedule, standings, bracket, schedule, explanations | Implemented and tested |
| `Swiss\SwissPairer`: Swiss rounds by score groups, exact up to 16 participants, bounded search above, deterministic | Implemented and tested |
| `RoundRobin\BergerTable`, `RoundRobin\Pools`: pools by the Berger tables, rests in the schedule, return legs with the sides swapped, pools filled in a snake | Implemented and tested |
| `Elimination\Bracket`, `Elimination\BracketState`: a single-elimination bracket by seeds, byes in the first round, prepared before its qualifiers are known | Implemented and tested |
| `Tiebreak\Tiebreakers`: Buchholz, cut Buchholz, Sonneborn-Berger, head-to-head, wins, mission points, and those of the application | Implemented and tested |
| `Contract\SeedingSource`, `Seeding\FixedSeeding`, `Seeding\RatingSeeding`: the first order by strength, by a list, or by the ratings of `tilttek/rating` | Implemented and tested |
| `Event\TournamentFinished`: the winner and the podium | Implemented and tested |
| Double elimination, a third-place match | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Tournaments and stages](doc/formats.md) | Declaring a tournament, its stages and its scoring, and running it round by round. |
| [Pairings](doc/pairings.md) | How Swiss rounds, pools and brackets are paired, the reasons given, and the limits of the Swiss pairing. |
| [Results, corrections and withdrawals](doc/results.md) | Entering a result once, conflicts, corrections, withdrawals, and the end of the tournament. |
| [Tie-breakers](doc/tiebreakers.md) | The standings, the tie-breakers of the library, their order, and the last draw. |
| [Extension points](doc/extension-points.md) | The store, the seeding source, the tie-breakers and the event. |
| [Exceptions](doc/exceptions.md) | `TournamentException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and its benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Stage;
use Tilttek\Tournament\Tournament;
use Tilttek\Tournament\Tournaments;

$tournaments = new Tournaments($store);             // your TournamentStore
$tournaments->start(new Tournament(
    id: 'spring-open',
    seed: 'spring-open-2027',                       // published: anyone can compute the pairings again
    participants: ['m-104', 'm-221', 'm-318', 'm-402', 'm-517'],
    stages: [Stage::swiss('swiss', rounds: 3, qualifiers: 4), Stage::singleElimination('top-4')],
    tiebreakers: ['buchholz', 'sonneborn-berger', 'wins'],
));

$round = $tournaments->prepareRound('spring-open'); // the tables, with their reasons, to show first
$tournaments->publishRound('spring-open', $round);

$tournaments->record('spring-open', $round->key(1), Result::firstWins(), 'm-104');
$tournaments->record('spring-open', $round->key(1), Result::firstWins(), 'm-318'); // Unchanged: counted once

$tournaments->standings('spring-open')->participants();
```

## Requirements

PHP 8.5 or later. `tilttek/rating` is suggested, for `Seeding\RatingSeeding`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
