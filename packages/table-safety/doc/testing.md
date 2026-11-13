# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the boundaries and levels, lines
and veils, the checklist, the themes of a table and their bounds, the agreement and its preferences, the questions,
templates, answers and pact of the session zero, the signals and the check-in tally, stars and wishes and their
summary, the settings and their storage as arrays, the memory stores through `StoresTestCase`, and `TableSafety` with
its export and erasure. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Testing an application that uses it

Build `TableSafety` on the memory stores and a small `Catalogue`, and pass the moments explicitly: every method that
writes takes the moment it happens at.
