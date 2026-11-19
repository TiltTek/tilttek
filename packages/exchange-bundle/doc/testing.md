# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests run the DBAL stores on SQLite in memory through the contract test cases of `tilttek/exchange`, the
locks, the holds and the rollback of a trade with the ledger on the same connection, moments of another zone kept in
UTC, `SpendingPointsHold` through the contract case every `PointsHold` passes and against `Redemptions`, the
configuration and the services, the forms, the commands and the privacy module. The functional test boots a small
application with the points, transfers and trades, a held reward expiring under another default zone, and a member
exported and erased. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

## On MySQL and PostgreSQL

`tests/Integration` passes the reservations, the offers and the journal of transfers through the same contracts on
MySQL 8.4 and PostgreSQL 17, and checks what SQLite does not show: the index of the due reservations as the server reads
it back, moments of another zone kept in UTC, the catalogue kept and read back, and a row already there ignored without
aborting the transaction around it. The servers are those of `phil/dev-tools`, in Docker; the URLs are read from
`TILTTEK_EXCHANGE_TEST_MYSQL_URL` and `TILTTEK_EXCHANGE_TEST_POSTGRESQL_URL`, else from `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL`. Without them, the suite is skipped:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/tilttek-exchange-bundle/tests/Integration
vendor/bin/phil-dev services down
```

The suite drops and creates the bundle's seven tables in the test database once per run, and empties them before each
test.

## Testing an application that exchanges

Set `store: memory` in the test environment, with the points bundle's own `store: memory`, and a clock you control:

```yaml
# config/packages/test/tilttek_exchange.yaml
tilttek_exchange:
    store: memory
```

Then call the services from a `KernelTestCase` and read the balances, the stores and the events your listeners heard. A
hold of points of your own passes the abstract `PointsHoldTestCase` of the bundle's tests, on a member granted 100
points.
