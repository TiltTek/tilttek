# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the declarations and their
refusals, results and scoring, the Swiss pairer and both matchers, the Berger tables and the pools, the bracket and its
state, the tie-breakers and the standings, the seeding, and the service from the start of a tournament to its podium,
with conflicts, corrections, withdrawals, schedules and explanations. Small tournaments written by hand check the
pairings, the sides and the standings table by table. `Seeding\RatingSeeding` is tested where `tilttek/rating` is
installed, and skipped otherwise. In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Unit/PropertyTest.php` plays whole tournaments with results drawn from fixed seeds, from 4 to 64 participants:
a Swiss stage never repeats a meeting or a bye that another pairing of the round avoids, and never at all when the
field holds twice as many participants as rounds; the same seed plays the same tournament; every pair of a pool meets
once a leg, with the sides swapped on the return leg and the rests where the pool is odd; and a bracket ends with one
champion, its byes in the first round only.

`tests/Benchmark/PairingBench.php` measures a Swiss round of 16 participants paired exactly, of 64 and 512 by the
bounded search, the Berger table of a pool of 20, and the standings of 512 participants with three tie-breakers.

## Testing code that runs tournaments

Give `Tournaments` an `InMemoryTournamentStore` and a `FixedSeeding`, so that the first order is the one the test
writes:

```php
$tournaments = new Tournaments(new InMemoryTournamentStore(), seeding: FixedSeeding::inOrder(['a', 'b', 'c', 'd']));
$tournaments->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::swiss('swiss', 3)]));
```
