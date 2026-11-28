# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the encounters live in
`Store\InMemoryEncounterStore`, and a test keeps an encounter whose draw pile is in a known order, so that each deal gives
the cards it expects; the shuffle of `start()` runs on a seeded `Mt19937` engine. In the monorepo, Infection holds the
package at 100 % of covered mutants.

An application tests its own `EncounterStore` against the rules of [the store](extension-points.md#the-store): nothing for
an identifier never kept, a new encounter kept once, a stale one refused, the version raised by one at each write, and the
piles, combatants and deck read back as they were kept. It tests its own `CardRanking` on every face of its deck: each one
known, the order it means, the ties, and the cards that reshuffle.
