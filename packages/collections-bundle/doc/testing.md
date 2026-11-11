# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests check the configuration and the services, the tables and the values of a row, the five stores on SQLite
in memory through copies of the library's store contracts, the catalog in the database, the computed dates, the
inventory, the dice appearance, the events, the commands, the Twig functions and the privacy module. The functional
test boots a small application — FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, the privacy bundle and the
journal of activity — and checks the tables in the generated schema, the item of the day given live, the events, a pack
opened and verified, the templates rendered in English and French, the commands and the erasure of a member. In the
monorepo, Infection holds the bundle at 100 % of covered mutants.

## Testing an application that uses it

Set `store: memory` in the test environment to keep the collections in memory, and replace the `clock` service by a
`MockClock` to place activities on a given day. Replace the `SeedSource` alias by a source of fixed seeds to know what a
pack draws.
