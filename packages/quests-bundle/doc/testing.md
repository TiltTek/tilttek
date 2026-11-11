# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite covers the configuration and the services it registers, the tables and the schema listener, the four
DBAL stores on SQLite in memory through the library's store contracts, the catalog in the database, the quests that
come back at the almanac's dates, the events, the Twig functions, the commands and the privacy module. The functional
suite boots a small application — FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, `phil/privacy-bundle`,
`tilttek/activity-bundle` and this bundle — and runs the quests, a duel, a quest that comes back, the templates, the
commands and a member's export and erasure end to end; it is skipped where the event dispatcher is missing. In the
monorepo, Infection holds the bundle at 100 % of covered mutants.

The insertions written once are run on SQLite; those of PostgreSQL and MySQL are checked by their text.

## Testing an application that uses it

Set `store: memory` in the test environment, record activities through the journal, and read `Quests`:

```yaml
when@test:
    tilttek_quests:
        store: memory
```
