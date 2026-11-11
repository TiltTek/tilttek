# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite runs the ledger on SQLite in memory — the library's ledger contract included —, checks the insertion
statements of each platform, the lock row of a payment, a race lost to another process, the schema listener, the commands, the
privacy module and the services the configuration builds. The functional suite boots an application with
FrameworkBundle, DoctrineBundle on SQLite, `phil/privacy-bundle` and `tilttek/activity-bundle`, and earns, spends,
expires, exports and erases points through them. With `PHIL_TEST_MYSQL_URL` set, the integration suite runs the
ledger contract on MySQL, and two payments from the same account that meet, the first one committed
while the second is about to take the lock row. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

`phil/privacy-bundle` is an optional integration, not installed with the bundle: its tests, and its bundle in the test
application, are skipped with their reason when it is missing, and a test checks that no privacy module is registered
then.

## Testing an application that moves points

Set `store: memory` in the test environment to keep the ledger in the process — and the journal of activity with
`tilttek_activity.store: memory` —, or keep `doctrine` on a test database to exercise the real insertion and locks:

```yaml
# config/packages/test/tilttek_points.yaml
tilttek_points:
    store: memory
```

Freeze the `clock` service (`MockClock`) to test caps, periods and expiry.
