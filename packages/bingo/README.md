# tilttek/bingo

**Bingo cards for a convention or a session: unique cards drawn from a pool by a server seed, squares ticked by
recorded gestures or called by the host, win patterns, server-verified wins, printed batches with labels the server
checks, live updates through Mercure, and the events and Stimulus hook of a celebration.**

A game draws its cards from a pool of squares: "attend a talk", "visit the dice booth", "roll a natural 20". Each
player gets a card of their own, unlike every other card of the game, drawn from a secret of the server alone: the
seed of card `n` is the HMAC-SHA256 of the game and the serial under the secret, so the same card is drawn again from
its serial — a printed card is checked by its number — and no player can tell the cards of others or choose their own.

A convention's card is ticked by what the player does: each square names the gesture that ticks it, by verb and
maybe object, and the server ticks the card as the activity journal of `tilttek/activity` records the gestures, then
unticks it when a gesture is cancelled. A completed pattern wins on its own. A session's card is ticked by the host,
who calls squares for every card at once; a card wins when its player claims it and the server finds a pattern among
the squares called. Either way, the player never ticks a card nor declares a win: the server checks the card against
its seed and its ticks. A session's cards may also be printed in batches: each shows a label, its serial and a check
code drawn from the secret, which the organiser types to check the card of whoever shouts.

The patterns are a row, a column, a diagonal, the full card, the four corners, or the application's own. A win
dispatches `BingoWon`; the card template and its Stimulus controller turn it into the DOM event `bingo:won`, which the
celebration of the PHIL UX kit plays on; the calls, the wins and the ticks reach the players' screens through Mercure.
It is a plain PHP library whose dependencies are `tilttek/activity`, `psr/clock` and `psr/event-dispatcher`;
`symfony/mercure` is needed only for the live updates.

## Status

| Part | State |
|---|---|
| `Bingo`: games, dealing, calling, gestures recorded and cancelled, claims and checks | Implemented and tested |
| `BingoGame`, `Card`, `TickMode`, `Claim` | Implemented and tested |
| `CardDealer`: layouts and labels from the server's secret, fingerprints | Implemented and tested |
| Printed batches: `Bingo::print()`, `SerialLabel`, `Bingo::verifyLabel()`, `View\PrintedCardView` and `templates/print.html.twig` | Implemented and tested |
| `Mercure\BingoPublisher`: the calls, wins and ticks pushed through Mercure | Implemented and tested |
| `Square`, `SquarePool`, `Trigger`, `Grid` | Implemented and tested |
| `WinPattern`, `WinPatterns`: row, column, diagonal, full card, four corners, custom | Implemented and tested |
| `Activity\BingoActivityObserver`: the cards ticked from the activity journal | Implemented and tested |
| Events: `BingoWon`, `SquareTicked`, `SquareCalled` | Implemented and tested |
| `View\CardView`, `View\CellView`, `templates/card.html.twig` and the Stimulus controller `card` | Implemented and tested |
| `Contract\BingoStore` with versions, and its store in memory | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Running a game](doc/running-a-game.md) | Pools, grids, patterns, dealing, ticking, calling and winning. |
| [Cards and their seed](doc/cards-and-seeds.md) | How a card is drawn, kept unique and checked. |
| [Printed cards](doc/printing.md) | Batches to print, their labels and their check. |
| [Live updates](doc/live-updates.md) | The calls, the wins and the ticks pushed through Mercure. |
| [Celebrations](doc/celebrations.md) | The events, the card template and its Stimulus controller. |
| [Storing](doc/storing.md) | The store contract, versions and concurrent ticks. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `BingoException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Bingo\{Bingo, CardDealer, Grid, Square, SquarePool, Trigger, WinPatterns};
use Tilttek\Bingo\Activity\BingoActivityObserver;
use Tilttek\Bingo\Store\InMemoryBingoStore;

$bingo = new Bingo(new InMemoryBingoStore(), new CardDealer($secret), $dispatcher, $clock);

$grid = new Grid(5);                                      // 5 × 5, free centre
$bingo->create('con-2026', new SquarePool([
    new Square('talk', 'Attend a talk', new Trigger('talk.attended')),
    new Square('dice-booth', 'Visit the dice booth', new Trigger('booth.visited', 'booth:dice')),
    // ... at least 24 squares
]), $grid, WinPatterns::line($grid));

$card = $bingo->deal('con-2026', 'member:44');           // the same card on every call
$journalObservers[] = new BingoActivityObserver($bingo); // gestures tick, a line wins: BingoWon
```

## Requirements

PHP 8.5 or later; `tilttek/activity` 0.1, `psr/clock` and `psr/event-dispatcher`. The templates need Twig, and the
controller Stimulus, only where the application uses them; the live updates need `symfony/mercure`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
