# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the clocks live in
`Store\InMemoryClockStore`, and the time is the `MockClock` of `symfony/clock`, moved forward by each test to the moment
it needs — a few seconds of a turn, the days of a game by correspondence, the length of a vacation. In the monorepo,
Infection holds the package at 100 % of covered mutants.

An application tests its own `ClockStore` against the rules of [the store](extension-points.md#the-store): nothing for an
identifier never kept, a new clock kept once, a stale one refused, the version raised by one at each write, and the time
control, players, vacations and moments read back to the millisecond.
