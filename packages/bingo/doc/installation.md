# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/activity` 0.1: the gestures that tick a convention's cards, and the observer of its journal.
- `psr/clock`: the time of each win.
- `psr/event-dispatcher`: the dispatcher of `BingoWon`, `SquareTicked` and `SquareCalled`.
- Twig and Stimulus only for the card template and its controller; see [Celebrations](celebrations.md).
- `symfony/mercure` only to push the moves of a game; see [Live updates](live-updates.md).

## Installing

```sh
composer require tilttek/bingo
```

## Wiring the library

```php
use Tilttek\Bingo\{Bingo, CardDealer};
use Tilttek\Bingo\Activity\BingoActivityObserver;

$bingo = new Bingo(
    $store,                              // Contract\BingoStore: the games and their cards
    new CardDealer($secret),             // the server's secret, at least 32 bytes, kept out of the code
    $dispatcher,                         // a PSR-14 dispatcher
    $clock,                              // a PSR-20 clock
);

$observer = new BingoActivityObserver($bingo);   // among the observers of the activity Journal
```

The secret draws every card: keep it in the application's secrets, never in a repository nor in the browser, and keep
it for as long as a game's cards are checked — another secret draws other cards. `Bingo`, `CardDealer` and the observer
keep nothing between two calls, so one instance serves every request of a worker; `Store\InMemoryBingoStore` keeps
everything in the process, for tests and scripts.
