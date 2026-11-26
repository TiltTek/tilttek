# tilttek/turn-clock

**A turn timer for the table: per-player budgets, Fischer or Bronstein increments, delays, days per move, pauses,
vacations and overtime.**

tilttek/turn-clock keeps the time of a turn-based game, as a chess clock does for two players and a game clock for a
table of up to 20. Each player has a budget, the same for all or one each to even the table; the clock runs for the
player whose turn it is, and passing the turn charges it.

- **Time controls.** An increment added after each turn (Fischer), the time spent given back up to the increment
  (Bronstein), a delay before the budget runs (simple delay), or the whole budget again at each turn — days per move, as
  in a game by correspondence.
- **Overtime.** When a budget runs out, the clock counts the time over, below zero, for the table to decide; or the player
  is out of time, their budget stays at zero and their turns are skipped, until the clock stops when every player is out.
- **Pauses and vacations.** The table pauses the clock and resumes it; a player declares vacations, during which their
  clock does not run.
- **The turn order.** The next player in seat order who is not out of time, or the player named; time added or taken away
  by hand.

The service reads the time from a PSR-20 clock and from nothing else. It tells when the turn in progress runs out of time,
for the application's scheduled task to check the clock at that moment. The facts of a clock — started, a turn started or
ended, the time expired, paused, resumed, a vacation declared, time adjusted, stopped — go to an observer, each once, by
identifiers only.

It is a plain PHP library: one service, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `TimeControl`, `Mode`, `Overtime`: budgets, increments, delays, days per move, overtime counted or forfeited | Implemented and tested |
| `TableClock`, `PlayerClock`, `Vacation`: a clock and its players' time, bounded and checked | Implemented and tested |
| `TurnClock`: create, start, pass, check, pause, resume, vacations, adjust, stop | Implemented and tested |
| `View\ClockView` and `View\PlayerTime`: the clock to show the table, and when the turn runs out | Implemented and tested |
| `Store\InMemoryClockStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, the scheduled checks, Mercure and a Stimulus display | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Clocks and time controls](doc/clocks.md) | Budgets, modes, overtime, turns, pauses, vacations, adjustments, stopping and the view. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the clock, and checking at the expiry. |
| [Exceptions](doc/exceptions.md) | `TurnClockException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\TurnClock\Mode;
use Tilttek\TurnClock\TimeControl;
use Tilttek\TurnClock\TurnClock;

$clocks = new TurnClock($store, $clock, $observer);              // your ClockStore, a PSR-20 clock, an observer

$clocks->create('table-4', ['player-1', 'player-2', 'player-3'],
    new TimeControl(10 * 60_000, Mode::Fischer, 5_000),           // ten minutes each, five seconds added per turn
    ['player-3' => 15 * 60_000],                                  // fifteen minutes for a new player
);
$clocks->start('table-4');

$clocks->pass('table-4', 'player-1');                             // player 1 ends their turn; player 2's runs
$clocks->pause('table-4');                                        // a break: no time counts
$clocks->resume('table-4');

$view = $clocks->view('table-4');                                 // send $view->toArray() to the table
// schedule $clocks->check('table-4') at $view->expiresAt

$clocks->create('mail-game', ['player-1', 'player-2'], TimeControl::daysPerTurn(3));   // three days per move
```

## Requirements

PHP 8.5 or later, with `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
