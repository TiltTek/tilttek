# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the rosters and their history live
in `Store\InMemoryRosterStore`, the clock is the `MockClock` of `symfony/clock`, an observer of the tests records the
events in order, and a store of the tests refuses every write to show what a conflict leaves. In the monorepo, Infection
holds the package at 100 % of covered mutants.

An application tests its own `RosterStore` against the rules of [the store](extension-points.md#the-store): nothing for
a campaign never kept, a new roster kept once, a stale one refused when it is kept or deleted with nothing written, the
version raised by one at each write, the entries kept in the same write and read back the newest first for one faction
up to the limit — the entries of a relation for both its factions —, and the history forgotten with the roster.

## Benchmarks

`tests/Benchmark/RosterBench.php` measures, on the largest roster the library takes — 64 factions, each with 16 ranks,
a standing with the party and 63 characters and 64 members, and 512 relations —, the roster viewed by its game master
and by a player, each turned into an array, and a standing shifted through the service, which checks the whole roster
anew. From the root of the monorepo:

```sh
composer bench
```
