# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the shelves live in
`Store\InMemoryShelfStore`, the catalogue in the in-memory store of `tilttek/game-catalog`, and its clock is the
`MockClock` of `symfony/clock`. In the monorepo, Infection holds the package at 100 % of covered mutants.

An application tests its own `ShelfStore` against the rules of [the store](extension-points.md#the-store): an empty
shelf at version 0 for a member never kept, a new shelf kept once, a stale shelf refused, the version raised by one at
each write, and the wishes read back in their order.
