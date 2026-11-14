# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on a clock the tests move; it
renders the result template with Twig. The script tests, under `tests/js`, run the helpers of the Stimulus controller
with Node.js. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

Two fuzz targets, under `tests/Fuzz`, feed the inputs a player types to the library: `pin`, any text read as a PIN,
which gives six ASCII digits or `InvalidQuiz`; `pseudonym`, any text as a guest's pseudonym and through the word list,
which answers the same way twice. In the monorepo, `composer fuzz` runs them.

## Benchmarks

`tests/Benchmark/QuizBench.php` measures one answer scored in a session of 1 000 players, the standings of that session
after ten questions, the standings of 1 000 players in ten teams, and a pseudonym checked against the library's word
list.
