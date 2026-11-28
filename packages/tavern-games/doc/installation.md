# Installation

## Requirements

- PHP 8.5 or later, with `ext-sodium`.
- `tilttek/game-engine` 0.1, which runs the tables, keeps their logs and draws their chance.
- `tilttek/dice` 0.3, which rolls the dice of the dice games.
- `tilttek/card-deck` 0.1, which keeps the cards of the card games.

## Installing

```sh
composer require tilttek/tavern-games
```

## Wiring the library

The library has no service to configure. An application lists its tavern games, gives the same games to the engine's
`Tables`, and gives `TavernTables` its turn timer when its turns have a delay:

```php
$games = new TavernGames(new Pig(), new ShipCaptainCrew(), new CrazyEights());
$tables = new Tables($games->games(), $logStore, $stateCache, $publisher);
$tavern = new TavernTables($tables, $games, new SchedulerTurnTimer($scheduler)); // the application's adapter
```

`TavernTables` and the games keep nothing between two calls; the `Tables` it is given keeps the states it read, and
its `reset()` lets them go between two requests of a worker. The store of the logs, the state cache and the publisher
are those of `tilttek/game-engine`.

`Store\InMemoryTurnTimer` is for tests: it keeps the running turns in the object, which a worker must never share
between requests.
