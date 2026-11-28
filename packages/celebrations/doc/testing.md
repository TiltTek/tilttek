# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory store and the
`MockClock` of `symfony/clock`: achievements and their bounds — keys, occurrences, parameters, the moment to stop being
shown —; receiving once, for one member and not another; groups by source and kind, their order to the microsecond and
by reception, the entries they show and count; the next celebration, the members' preferences and the device's reduced
motion; marking seen by sequence, by group and all at once; withdrawals told twice; entries lapsing when their moment
comes and when the pending bound is reached; the inbox dropping its first settled entries beyond its bound while
keeping its pending ones; history pages; erasure, with sequences never reused; and the events told. In the monorepo,
Infection holds the library at 100 % of covered mutants.

The bench, under `tests/Benchmark`, fills an inbox to the default limits — 200 pending entries from four sources among
500 — and measures the count, the next celebration and an achievement received at the bound.

## Testing code that uses the inbox

`Store\InMemoryCelebrationStore` and a `MockClock` give a whole inbox in a test. A listener of the application is tested
by handing it the rewards package's event and reading `pending()`, or by an observer that records the
`CelebrationChanged` it is told.
