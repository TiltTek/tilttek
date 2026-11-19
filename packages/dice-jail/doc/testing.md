# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: the p-value against the published quantiles
of the chi-squared law and the closed forms of two, four and six degrees; exact statistics on small tallies whose
p-value is e^(−x/2); the minimum of rolls in whole rolls; 2 000 fair dice from a fixed seed crossing the lines as
rarely as they should, and a worn die found; every sentence in English and French; the jail's sentences, releases,
history, limits and refusals with a mock clock. In the monorepo, Infection holds the library at 100 % of covered
mutants.

`tests/Fuzz/tally.php` is the fuzz target of the test: any bytes, read as rolls, give a p-value between 0 and 1 and an
explanation, or a refusal. `composer fuzz` runs it.

`tests/Benchmark/GoodnessOfFitBench.php` measures 10 000 rolls of a d20 counted, tested and explained, and the test of
a d100.

## Testing an application

Give the warden a `Symfony\Component\Clock\MockClock` and move it to end a sentence; record rolls in an
`InMemoryRollSource` with their time to test `since`.
