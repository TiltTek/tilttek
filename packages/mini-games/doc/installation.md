# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/game-engine` 0.1, which runs the tables, keeps their logs and draws their chance.

## Installing

```sh
composer require tilttek/mini-games
```

## Wiring the library

The library has no service to configure. An application lists its mini-games, gives the same games to the engine's
`Tables`, and gives `DailyGames` a secret of at least 32 bytes and, when it ranks its players, a score sink:

```php
$games = new MiniGames(new BullsAndCows(), new HigherOrLower(), new MyWordGame());
$tables = new Tables($games->games(), $logStore, $stateCache, $publisher);
$daily = new DailyGames($tables, $games, new DailySeed($secret), new LeaderboardScoreSink($activities));
```

The secret is a value of the application's configuration, kept like any other secret: with it, anyone could compute
the games of the days to come. Changing it changes every game from the next table opened on.

`DailyGames` keeps nothing between two calls; the `Tables` it is given keeps the states it read, and its `reset()` lets
them go between two requests of a worker. The store of the logs, the state cache and the publisher are those of
`tilttek/game-engine`.

`Store\InMemoryScoreSink` is for tests: it keeps its scores in the object, which a worker must never share between
requests.
