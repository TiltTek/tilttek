# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel or a network: the log store on SQLite in memory — logs
created, appended, taken back and read back as they were written, headers and revisions, moves read window by window,
concurrent moves and moves made before a move was taken back refused, a column that is not JSON refused, tables deleted
—, the seat identifiers that name a person refused, the tables in the schema of their connection only and the SQL of
MySQL and PostgreSQL, the state cache with what its pool gives back refused and a later revision never written over, the
lock held for the work and released after a failure, the Mercure publisher and a hub that fails, the endpoints for a
seat, the audience, a reader refused and a game that goes on, the commands, the Twig functions and the configuration.
`tests/js` checks the reading of an update, with `node --test`. In the monorepo, Infection holds the package at 100 % of
covered mutants.

`tests/Integration` runs the same tests of the log store against MySQL and PostgreSQL. Each reads its URL from
`TILTTEK_GAME_ENGINE_TEST_MYSQL_URL` or `TILTTEK_GAME_ENGINE_TEST_POSTGRESQL_URL`, else from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services env` sets, and is skipped without either.

`tests/Functional` boots an application with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, Twig components, an
in-memory lock and a Mercure hub that records: the tables are created by the command, a table is played and published,
read from the endpoints, its template rendered with the recipes of the kit when one is found, its log verified, and
no move taken back once its game is over.

`tests/Benchmark/StorageBench.php` measures a move appended to the log and a log of 100 moves read back, on SQLite in
memory, and a state put in an array pool and read back: about 22 µs, 0.34 ms, 9 µs and 13 µs on PHP 8.5 without its
opcode cache — the cost of the bundle's own work, before the time of a server.

`tests/Benchmark/LargeTableBench.php` measures a table of 8 000 moves of 16 000 bytes each: the view of a seat, as
`GET /{table}` reads it, and a move, both on a cached state, then a replay from the setup, window by window — about
71 µs, 110 µs and 0.54 s.

## Testing an application's game

Configure the `memory` store in the test environment, so that no database is needed, and give the tables a seed, so
that their chance is the same at every run:

```yaml
# config/packages/test/tilttek_game_engine.yaml
tilttek_game_engine:
    store: memory
    cache: { pool: ~ }
```

```php
$tables = static::getContainer()->get(GameTables::class);
$tables->open('chess', 'table:1', ['seat:1', 'seat:2'], [], str_repeat('0', 64));
```
