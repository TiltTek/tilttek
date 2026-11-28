# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the rulebooks live in
`Store\InMemoryRulebookStore`, and the time is the `MockClock` of `symfony/clock`, moved forward by each test to the
moment it needs. The tests cover each bound at its limit and one past it, each state of a player, and the comparison of
rules added, removed, moved and changed line by line. In the monorepo, Infection holds the package at 100 % of covered
mutants.

An application tests its own `RulebookStore` against the rules of [the store](extension-points.md#the-store): nothing
for a table never kept, a new rulebook kept once, a stale one refused, the revision raised by one at each write, a
version kept only with its rulebook, and the versions, decisions and moments read back as they were kept.

It tests its `VariantCatalogue` by publishing a version with a variant its system offers, and one it does not.
