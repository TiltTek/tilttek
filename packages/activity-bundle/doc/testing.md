# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite runs the journal and the checkpoints on SQLite in memory — the library's journal contract included —,
checks the insertion statement of each platform, the schema listener, the commands, the privacy module and the
services the configuration builds. The unit suite also sends the projections' messages to a bus that writes them down and
handles them twice. The functional suite boots an application with FrameworkBundle, DoctrineBundle on SQLite and
`phil/privacy-bundle`, once with live projections and once with projections through Messenger on a transport in memory. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

`phil/privacy-bundle` is an optional integration, not installed with the bundle: its tests, and its bundle in the test
application, are skipped with their reason when it is missing, and a test checks that no privacy module is registered
then.

## Testing an application that records activity

Set `store: memory` in the test environment to keep the journal in the process, or keep `doctrine` on a test database
to exercise the real insertion. Read the journal back through the `Journal` service:

```yaml
# config/packages/test/tilttek_activity.yaml
tilttek_activity:
    store: memory
```
