# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests cover the configuration and the services it wires, the tables and the schema listener, the four DBAL
stores on SQLite through the library's contracts, the observer and its events, the Twig functions, the privacy module,
the commands, the schedule and its handler, the scores in Valkey and the names of the kit's recipes. The functional
tests boot a small application — FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and Twig components, the privacy
bundle, the journal of activity, the recipes of the PHIL UX kit copied where `ux:install` puts them — and check the
tables in the generated schema, the scores kept live and a cancellation, a reported value, the template rendered by each
member's choice, the snapshot command and its event, a weekly league over two weeks — its division rendered, settled by
the command then by the scheduled message —, the league screen and the ranking of teams built of the kit's recipes, and
a member exported and erased. The league screen is skipped when the PHIL UX kit is neither beside the monorepo nor
installed. They are skipped when the event dispatcher, a development dependency, is missing. In the monorepo, Infection
holds the bundle at 100 % of covered mutants.

## On MySQL and PostgreSQL

`tests/Integration` passes the four DBAL stores through the same contracts on MySQL 8.4 and PostgreSQL 17, and checks
what SQLite does not show: the index of the ranking as the server reads it back, a moment of another zone kept in UTC,
and a row already there ignored without aborting the transaction around it. The servers are those of
`phil/dev-tools`, in Docker; the URLs are read from `TILTTEK_LEADERBOARDS_TEST_MYSQL_URL` and
`TILTTEK_LEADERBOARDS_TEST_POSTGRESQL_URL`, else from `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`. Without
them, the suite is skipped:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/tilttek-leaderboards-bundle/tests/Integration
vendor/bin/phil-dev services down
```

The suite drops and creates the bundle's nine tables in the test database once per run, and empties them before each
test.

## Testing an application that uses it

Configure `store: memory` in the test environment, record activities through the `Journal` service, and read
`Leaderboards`; a `MockClock` as the `clock` service moves time to the end of a period before taking its snapshot, or to
the end of a week before settling it with `Leagues::settleDue()`.
