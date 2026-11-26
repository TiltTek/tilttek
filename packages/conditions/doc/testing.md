# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the conditions live in
`Store\InMemoryConditionStore`, and the time is the `MockClock` of `symfony/clock`, moved forward by each test to the
moment it needs — the end of a condition in real time, to the millisecond. A small catalogue of the tests holds a
condition of each kind: replaced, highest, summed, separate, ignored, decaying, brought by others, in game time, in real
time and on the source's turns. In the monorepo, Infection holds the package at 100 % of covered mutants.

An application tests its own `ConditionStore` against the rules of [the store](extension-points.md#the-store): nothing
for a character never kept, new conditions kept once, stale ones refused, the version raised by one at each write, and
the applications, durations, moments and last tick read back as they were kept.

It tests its catalogue by building it: a definition or a catalogue whose rules contradict one another is refused at
construction, with the reason.
