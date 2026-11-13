# Installation

## Requirements

- PHP 8.5 or later.
- `symfony/workflow`, the state machine of the phases.
- `symfony/options-resolver`, which checks the options of a table.

## Installing

```sh
composer require tilttek/game-engine
```

## Wiring the library

`Tables` takes the games an application offers, a store of logs, and optionally a cache of states and a publisher:

```php
$tables = new Tables(
    new Games(new RoleplayingScene(), new MyCardGame()),
    $logStore,            // Contract\LogStore: the truth of every table
    $stateCache,          // Contract\StateCache: NullStateCache by default, every read replays
    $publisher,           // Contract\TablePublisher: NullTablePublisher by default
);
```

The stores in memory forget everything with the process; an application writes its own on its storage
([Storing and publishing](storing-and-publishing.md)). `Engine` alone runs a table without any store, for a script or
a test ([Tables, moves and turns](tables-and-moves.md)).

`Engine`, `Tables` and the games keep no state between two calls, so one instance serves every request of a worker;
`InMemoryStateCache` and `InMemoryLogStore` do keep state, and are for tests and scripts. `Tables` keeps the states it
read while their log does not change; a worker calls its `reset()` between two requests.
