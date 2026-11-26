# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the series and sessions live in
`Store\InMemorySessionStore`, and the clock is the `MockClock` of `symfony/clock`, set to each moment a test needs. The
tests cross a change of time in `America/Toronto`, so that the wall clock of the sessions is checked. In the monorepo,
Infection holds the package at 100 % of covered mutants.

An application tests its own `SessionStore` against the rules of [the store](extension-points.md#the-store): nothing
for an identifier never kept, a new series or session kept once, a stale one refused, the version raised by one at each
write, the replies read back by member, each `SessionQuery` giving the sessions `SessionQuery::matches()` accepts, the
earliest first, and `countComing()` counting only the planned sessions after the moment.
