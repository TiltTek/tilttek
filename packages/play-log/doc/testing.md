# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the plays live in
`Store\InMemoryPlayStore`, the catalogue in the in-memory store of `tilttek/game-catalog`, and its clock is the
`MockClock` of `symfony/clock`. In the monorepo, Infection holds the package at 100 % of covered mutants.

An application tests its own `PlayStore` against the rules of [the store](extension-points.md#the-store): no play for
an identifier never kept, a new play kept once, a stale play refused on saving and on deleting, the version raised by
one at each write, the seats read back in their order, and each `PlayQuery` giving the plays `PlayQuery::matches()`
accepts, the latest first, those whose date is unknown last.
