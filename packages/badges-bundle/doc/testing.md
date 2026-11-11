# Testing

## The bundle's tests

`tests/Unit` checks the configuration and the services, the tables and the schema listener, the four DBAL stores on
SQLite in memory against the same contracts as the library's stores, the events, the Twig functions, the credentials,
the privacy module and the commands. `tests/Functional` boots an application — FrameworkBundle, DoctrineBundle on
SQLite, TwigBundle, the privacy bundle, the journal of activity, `phil/open-badges-bundle` — that earns, withdraws, shows, renders, issues,
exports and erases.

```sh
vendor/bin/phpunit
```

`phil/open-badges-bundle` and `phil/privacy-bundle` are optional integrations, not installed with the bundle: their
tests, and their bundles in the test application, are skipped with their reason when they are missing, and a test checks
that no privacy module is registered then.

Every mutant Infection makes of the bundle's code is killed by a test: the bar is 100 % of covered mutants.

## Testing an application that awards badges

Set `store: memory` in the test environment, record activities in the `Journal` and assert on `Badges`; listen to
`BadgeAwarded` to check a notification.
