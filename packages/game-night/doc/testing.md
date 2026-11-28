# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the nights live in
`Store\InMemoryNightStore`, the catalogue in the in-memory store of `tilttek/game-catalog`, and the clock is the
`MockClock` of `symfony/clock`. In the monorepo, Infection holds the package at 100 % of covered mutants.

An application tests its own `NightStore` against the rules of [the store](extension-points.md#the-store): a new night
kept once, a stale night refused, the version raised by one at each write.
