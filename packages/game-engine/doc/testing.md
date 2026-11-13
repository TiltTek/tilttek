# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: definitions and their refusals, options,
the phases on Symfony Workflow and their diagram, turns of every order, initiative and delays, the engine with a small
race game and with the role-playing scene, the log written and read back, deterministic replays — a table of thirty
moves replayed from its JSON to an equal state —, replays refused for a changed version or a game that draws
differently, verified replays against the commitment to the seed, undo, preferences and statistics, states written and
read back with everything else refused, and `Tables` with its stores, cache, concurrent moves and publisher: the undo
refused once the game is over, a stale state of the same number of moves never served nor written back while two
writers interleave, a log read again when it changes during the read, and a table read once per request. In the
monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/log.php` is the fuzz target of the logs: any JSON text is refused with the library's exceptions or gives a
log that replays, and whose log written and read back replays to the same state. `tests/Fuzz/state.php` is that of
the states, and `tests/Fuzz/snapshot.php` that of the snapshots a shared cache gives back: any JSON text is refused with
the library's exceptions or gives a state, or a snapshot, that written and read back is the same. `composer fuzz` runs
the three.

`tests/Benchmark/ReplayBench.php` measures one move played on a state, a log of 100 and of 1 000 moves replayed, and a
log read back from its plain values.

## Testing a game

Give the table a seed, so that its chance is the same at every run, play the moves, and check the state; then check
that the log replays to the same state:

```php
$engine = new Engine();
$table = $engine->start($game, 'table:1', ['ann', 'bob'], [], str_repeat('0', 64));
$table = $engine->play($game, $table, new Move('race.roll', 'ann'));

self::assertEquals($table->state, $engine->replay($game, GameLog::fromArray($table->log->toArray())));
```

The replay test catches a game that reads a clock, a service or a global: its replay draws or decides differently.
