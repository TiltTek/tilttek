# tilttek/score-sheets

**Score sheets declared per game, with categories, totals computed live and tie-breakers, printable blank, and results
passed on to the play log.**

tilttek/score-sheets keeps the score of a board game the way its sheet does, from a declaration that is data, not
code: the categories in order, each entered by the scorer between its bounds or computed from those before it — a
sum, a product, points by place for a majority, a simple conditional bonus —, which of them count in the total, and
the tie-breakers. The scoreboard is computed again at each entry: every value, every total, the ranks, and the steps
that explain each value; team scores are composed of their members' totals, with the detail of the sum.

The sheet of a match is prepared before the players see it, published, filled, and closed; a sheet closed too early
is reopened, corrected and closed again, as a new revision. A closed match gives a neutral result — places, totals,
teams, revision — and a message with its identifier only, for a play log or a rating to read, without depending on
either. A blank sheet to print comes as neutral data: the layout, in HTML or PDF, belongs to the application.

It is for an application that keeps scores at the table — a game night, a tournament, a club's play log. It is a plain
PHP library with no dependency.

## Status

| Part | State |
|---|---|
| `SheetDefinition`, `Category`, `Tiebreaker`: declarations as data, read from JSON and written back | Implemented and tested |
| `Operation\*`: sum, product, majority and bonus, and their codec | Implemented and tested |
| `Entries`, `Team`: participants, teams, and values entered, corrected or erased | Implemented and tested |
| `Scoring\*`: totals, ranks with tie-breakers, explanations and team scores | Implemented and tested |
| `ScoreKeeper`, `MatchSheet`, `SheetStatus`: prepare, publish, enter, close and reopen a match's sheet | Implemented and tested |
| `MatchResult`, `Placement`, `Message\MatchResultRecorded`: the result passed on | Implemented and tested |
| `BlankSheet`: the blank sheet to print, as neutral data | Implemented and tested |
| `Contract\MatchSheetStore`, `Store\InMemoryMatchSheetStore`: the store port, and the store in memory for tests | Implemented and tested |
| A store on Doctrine DBAL, the HTML or PDF layout of a sheet, a Symfony bundle | Not started: adapters of the application, or of a future bundle |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Declaring a sheet](doc/declaring-sheets.md) | Categories, operations, tie-breakers and teams, in JSON or in code. |
| [Scoring](doc/scoring.md) | Entries, totals, ranks, explanations and team scores. |
| [Match sheets](doc/match-sheets.md) | Preparing, publishing, closing and reopening a sheet; versions; the store. |
| [Passing results on](doc/results.md) | The neutral result and its message, for a play log or a rating. |
| [Printing a blank sheet](doc/printing.md) | The blank sheet as neutral data. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `ScoreSheetsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\MatchId;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\ScoreKeeper;
use Tilttek\ScoreSheets\SheetDefinition;

$sheet = SheetDefinition::fromJson(file_get_contents('sheets/lantern-harbor.json'));
$keeper = new ScoreKeeper($store); // your Contract\MatchSheetStore
$match = new MatchId('m-2031');
$ada = new ParticipantId('u-101');
$bo = new ParticipantId('u-102');

$keeper->prepare($match, $sheet, new Entries([$ada, $bo]));
$keeper->publish($match, 0);

$board = $keeper->record($match, 1, $ada, 'boats', 5);   // the scoreboard, computed again
echo $board->participant($ada)->total;

$result = $keeper->close($match, 2);
$bus->dispatch($result->message());                      // the match and the revision only
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
