# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests cover the configuration and the services, the tables and the schema listener, the three DBAL stores on
SQLite in memory through copies of the library's contract test cases, the events, the Twig functions, the commands and
the privacy module. The functional test boots a small application — FrameworkBundle with its translator, DoctrineBundle
on SQLite, TwigBundle, `phil/privacy-bundle`, `tilttek/activity-bundle` — and records activities: levels, a milestone,
a cancellation, the templates rendered, the sweep, the show command and a member exported and erased. In the
monorepo, Infection holds the bundle at 100 % of covered mutants.

`phil/privacy-bundle` is an optional integration, not installed with the bundle: its tests, and its bundle in the test
application, are skipped with their reason when it is missing, and a test checks that no privacy module is registered
then.

## Testing an application that uses it

Configure `store: memory` in the test environment, record activities through `Tilttek\Activity\Journal`, then read
`Tilttek\Progression\Progression`. Freeze the `clock` service to test streaks and seasons.
