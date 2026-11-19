# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests run the DBAL stores on SQLite, in a file in write-ahead logging with a second connection for the other
writer, through the contract test case `StoresTestCase`: the entries kept and read back as they were written, the
indexes of the balances, the holders, the operations and the sessions, a writer refused after a position no longer
last, a writer that meets another between its read and its insertion — a middleware of the tests runs the other one
then —, an operation of the library read again twice and written the third time, the same operations on the store and
on the library's memory ledger, a member erased in every table, and the rules kept and read back. They also cover the
tables and the schema listener, the configuration and the services, `LockedLuckTokens` and the locks it takes and
releases, the voter and the access through the security component, and the erasure module. The functional test boots
a small application with the locks enabled and erases a member through the privacy bundle. In the monorepo, Infection
holds the bundle at 100 % of covered mutants.

## On MySQL and PostgreSQL

`tests/Integration` passes the same `StoresTestCase` on MySQL 8.4 and PostgreSQL 17 — the other writer on its own
connection, references that differ only by case kept apart —, and runs four processes granting to the same holder of
the same table at once, on SQLite, MySQL and PostgreSQL, with and without the locks: every grant is written once, at
positions that follow one another, each balance one more than the one before; under the locks, no process meets
another. The servers are those of `phil/dev-tools`, in Docker; the URLs are read from
`TILTTEK_LUCK_TOKENS_TEST_MYSQL_URL` and `TILTTEK_LUCK_TOKENS_TEST_POSTGRESQL_URL`, else from `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL`. Without them, the server tests are skipped:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/tilttek-luck-tokens-bundle/tests/Integration
vendor/bin/phil-dev services down
```

The suite drops and creates the bundle's two tables in the test database once per run, and empties them before each
test.

## The benchmark

`tests/Benchmark/DbalLuckTokensBench.php` measures, on SQLite in memory, a token spent and granted back after 10 000
entries, and the start of a session for twelve players, beside the memory ledger of the library's own benchmark. On the
machine of the measures, a spending and a grant take about 0.19 ms together in the database, against 18 µs in memory;
the start of a session, which reads the holders of the whole ledger, about 2.6 ms.

## Testing an application

Set `store: memory` in the test environment, and a clock you control:

```yaml
# config/packages/test/tilttek_luck_tokens.yaml
tilttek_luck_tokens:
    store: memory
```

Then call `LuckTokens` from a `KernelTestCase` and read the balances, the ledger and the events your listeners heard. A
store of your own extends the abstract `StoresTestCase` of the bundle's tests.
