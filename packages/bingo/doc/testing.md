# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: layouts pinned for a secret, unique
cards, gestures recorded and cancelled, calls, claims on a forged card or another player's, conflicts in the store, and
the card template and a printed batch rendered with Twig. The script tests, under `tests/js`, run the helper of the
Stimulus controller with Node.js. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

The fuzz target `label`, under `tests/Fuzz`, reads any text as the label of a printed card: it gives a label that,
written and read back, is the same, or `InvalidBingo`; checked against a game with a printed batch, it is refused or
names the card of its serial. In the monorepo, `composer fuzz` runs it.

## Benchmarks

`tests/Benchmark/BingoBench.php` measures the layout of a 5 × 5 card drawn from 500 squares, the check of every pattern
of a card, and one gesture recorded for a member who holds a card.
