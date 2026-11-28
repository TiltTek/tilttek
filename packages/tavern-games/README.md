# tilttek/tavern-games

**Classic dice and card games for a tavern table online, turn by turn: fair dice and shuffles, each player sent only
their own cards, ties shown as ties.**

tilttek/tavern-games plays traditional tavern games, free of rights, at tables of `tilttek/game-engine`, with rules
written for this library. Pig and Ship, Captain and Crew are dice games; their dice are rolled by `tilttek/dice` with
the chance of the table, so that replaying a table rolls the same dice. Crazy Eights is a card game; its cards are kept
by `tilttek/card-deck`, shuffled from a seed drawn from the table's own, and each player sees their own hand, the top of
the discard pile and how many cards the others hold — never their cards nor the order of the draw pile.

A tavern game keeps its own turns: a die that shows 1 or a card played ends a turn by the game's rules. The timeout of a
turn goes through an optional port: an application that gives a turn timer is told whose turn starts and calls back
when the delay runs out, and the game ends that turn the way its rules say, never with a penalty beyond the turn.
Without a timer, a turn lasts until it is played. An estimator counts the odds of a position over simulated endings,
with wins, ties and losses apart and the number of endings they were counted on, so that a shared win is shown as
itself. The end of a game is told in one of three tones: a victory, a shared victory — both celebrated — or a good
game, told kindly and never mocked.

It is for an application that hosts games played at a distance — a game night, a site's tavern — and wants every table
auditable and fair. It is a plain PHP library whose dependencies are `tilttek/game-engine`, `tilttek/dice` and
`tilttek/card-deck`. A game of a publisher is published only with the publisher's consent on record; the library holds
none.

## Status

| Part | State |
|---|---|
| `Contract\TavernGame`, `Turns`: turn-based games that keep their own turns and accept the timeout of a turn | Implemented and tested |
| `TavernTables`, `Contract\TurnTimer`, `Store\NullTurnTimer`, `Store\InMemoryTurnTimer`: tables opened and played, the turn timer told, late timeouts ignored | Implemented and tested |
| `Ending`, `Tone`: a victory, alone or shared, celebrated; any other ending told kindly | Implemented and tested |
| `DiceCup`: six-sided dice rolled by `tilttek/dice` with the chance of a move | Implemented and tested |
| `Odds\Estimator`, `Odds\Estimate`, `Odds\Trial`, `Contract\Simulation`: odds over simulated endings, ties apart | Implemented and tested |
| `Game\Pig`, `Game\ShipCaptainCrew`, `Odds\CargoChase`: two dice games, and the odds of a turn of the second | Implemented and tested |
| `Game\CrazyEights`: a card game on `tilttek/card-deck` | Implemented and tested |
| `TavernGames`: the games of an application, by name | Implemented and tested |
| Exceptions under one `TavernGamesException` interface | Implemented and tested |
| Real-time games, a Symfony bundle, its controllers and Stimulus controllers | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Tables and turns](doc/tables-and-turns.md) | `TavernTables`, the game's own turns, the turn timer and timeouts, endings. |
| [Writing a tavern game](doc/writing-a-tavern-game.md) | The `TavernGame` contract, turns, dice, cards and the rules of the library's games. |
| [The dice games](doc/dice-games.md) | Pig and Ship, Captain and Crew: their moves, options, views and outcomes. |
| [Crazy Eights](doc/crazy-eights.md) | The card game, its cards on `tilttek/card-deck`, what each seat sees, and its endings. |
| [Odds](doc/odds.md) | The estimator, wins, ties and losses apart, and the chase of a cargo. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `TavernGamesException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks, and testing a tavern game. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\GameEngine\{Move, Tables};
use Tilttek\TavernGames\{TavernGames, TavernTables};
use Tilttek\TavernGames\Game\{CrazyEights, Pig, ShipCaptainCrew};

$games = new TavernGames(new Pig(), new ShipCaptainCrew(), new CrazyEights());
$tavern = new TavernTables(new Tables($games->games(), $logStore), $games, $turnTimer);

$tavern->open('crazy-eights', 'table:7', ['seat:ann', 'seat:bob', 'seat:cid']);
$tavern->view('table:7', 'seat:ann');               // Ann's hand, the top card, the others' counts
$tavern->play('table:7', new Move(CrazyEights::DRAW, 'seat:ann'), expected: 0);
$tavern->timeout('table:7', 'seat:ann', 1);           // the timer ran out: Ann passes
$tavern->ending('table:7', 'seat:bob')?->tone;        // Victory, Tie or GoodGame once it is over
```

## Requirements

PHP 8.5 or later, `ext-sodium`, `tilttek/game-engine` 0.1, `tilttek/dice` 0.3 and `tilttek/card-deck` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
