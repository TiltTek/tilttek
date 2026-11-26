# tilttek/mini-games

**Small games played alone on a site, with a daily game everyone shares, results shared without spoilers, and no
streak that punishes a missed day.**

tilttek/mini-games is the base of a site's mini-games, built on `tilttek/game-engine`. A mini-game is a game of the
engine played at a table of one seat that also declares which way its scores go — the most points or the fewest
guesses —, the keys that play it, and how a result is shared. The daily game gives every player of a day the same
game: its seed is an HMAC of the game and the day under a key derived from a secret of the site, so nobody can work
out tomorrow's game, and the seed is shown, for anyone to verify the draws, once the day is over. A result is shared as a grid of text —
coloured squares or plain characters — that tells how each step went and never what it was.

The end of a game is told in one of two tones: a victory, which the application celebrates, or a game completed,
told kindly and never mocked. A record is set by a better score only, never by an equal one. A player's history counts
the days played, the wins, the best score and the spread of scores; it counts no streak, so a missed day costs
nothing. Every game is played with the keyboard alone, and none needs an animation. The score of each finished daily
game goes to a port, which an application connects to its leaderboards or its activity journal.

Two games come with it as models, both traditional games of pencil and paper: Bulls and Cows, where the fewer
guesses the better, and Higher or Lower, where the more right calls the better.

It is for an application that offers small games, daily or not, and wants them fair, shareable and gentle. It is a
plain PHP library whose one dependency is `tilttek/game-engine`.

## Status

| Part | State |
|---|---|
| `Contract\MiniGame`, `Scoring`, `ScoreOrder`, `Control`: a game of the engine that declares its scores and its keys | Implemented and tested |
| `DailySeed`, `Day`, `DailyGames`: the daily game, the same for every player of a day, its seed shown once the day is over | Implemented and tested |
| `ShareGrid`, `Cell`, `CellSymbols`: a result shared as text without spoilers | Implemented and tested |
| `Ending`, `Tone`: a victory celebrated, any other ending told kindly; records set by better scores only | Implemented and tested |
| `DailyScore`, `DailyHistory`, `Contract\ScoreSink`, `Store\NullScoreSink`, `Store\InMemoryScoreSink`: scores sent to a port, and a history without streaks | Implemented and tested |
| `MiniGames`: the games of an application, by name | Implemented and tested |
| `Game\BullsAndCows`, `Game\HigherOrLower`, `Game\Code`: two example games | Implemented and tested |
| Exceptions under one `MiniGameException` interface | Implemented and tested |
| A Symfony bundle, its controllers, templates and Stimulus controllers | Not started: adapters of the application, or of a future bundle |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Writing a mini-game](doc/writing-a-mini-game.md) | The `MiniGame` contract, scoring, controls, sharing, and playing without animation. |
| [Daily games](doc/daily-games.md) | The seed of the day, the player's table, the score sink, and verifying a day's game. |
| [Endings, records and history](doc/endings-and-history.md) | The tone of an ending, the celebration, records, and a history without streaks. |
| [Sharing a result](doc/sharing.md) | The grid, its cells, its symbols and what it never tells. |
| [The example games](doc/example-games.md) | Bulls and Cows and Higher or Lower, their moves, views and shares. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `MiniGameException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmark, and testing a mini-game. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\MiniGames\{DailyGames, DailySeed, Day, MiniGames};
use Tilttek\MiniGames\Game\{BullsAndCows, HigherOrLower};

$games = new MiniGames(new BullsAndCows(), new HigherOrLower());
$daily = new DailyGames(new Tables($games->games(), $logStore), $games, new DailySeed($secret), $scoreSink);
$today = Day::of(new DateTimeImmutable(), new DateTimeZone('America/Toronto'));

$daily->start('higher-or-lower', $today, 'u-101');
$daily->play('higher-or-lower', $today, 'u-101', HigherOrLower::CALL, ['call' => 'higher']);
$daily->view('higher-or-lower', $today, 'u-101');                       // the numbers shown so far
$daily->share('higher-or-lower', $today, 'u-101')->toText('Higher or Lower 12');
$daily->ending('higher-or-lower', $today, 'u-101')?->tone->celebrates(); // true for a victory only
```

## Requirements

PHP 8.5 or later, and `tilttek/game-engine` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
