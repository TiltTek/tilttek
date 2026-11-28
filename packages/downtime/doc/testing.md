# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the entries live in
`Store\InMemoryEntryStore`, the rules in `Rules\InMemoryRuleBook`, a roster of the tests holds one game master per
campaign and one player per character, a roller of the tests gives the totals it is given and records the expressions
it was asked to roll, and an observer of the tests records the events in order. In the monorepo, Infection holds the
package at 100 % of covered mutants.

An application tests its own `EntryStore` against the rules of [the store](extension-points.md#the-store): nothing for
an identifier never kept, a new entry kept once, a stale one refused, a list kept all or none, the version raised by one
at each write, the activity read back whole, and the three reads in the order chosen, filtered by campaign, character
and stage, up to the limit. It tests its own roster for a game master, a player, and a member who is neither, and its
roller for totals within the range of each expression.

## Benchmarks

`tests/Benchmark/AdvanceBench.php` measures, on the largest campaign one advance reads — 125 characters, each with a
full queue of 8 activities, 1000 entries —, an advance by one day through the service: the 1000 entries read, the
first of each queue advanced and the 125 changed kept at once. It also measures the largest rules a system declares —
64 activities of 16 outcomes and 16 bands — checked whole. From the root of the monorepo:

```sh
composer bench
```
