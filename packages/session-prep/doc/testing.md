# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the preparations live in
`Store\InMemoryPreparationStore`, and an observer of the tests records the events in order. In the monorepo, Infection
holds the package at 100 % of covered mutants.

An application tests its own `PreparationStore` against the rules of [the store](extension-points.md#the-store):
nothing for an identifier never kept, a new preparation kept once, a stale one refused when it is kept or deleted, the
version raised by one at each write, the parts read back in their order with their facts, and each `PreparationQuery`
giving the preparations `PreparationQuery::matches()` accepts, by identifier.

## Benchmarks

`tests/Benchmark/PreparationBench.php` measures, on the largest preparation the library takes — 50 scenes featuring 20
characters and 10 places each, 50 secrets of 10 clues, 100 characters, 50 places, 100 notes and 50 items —, a secret
revealed and hidden again through the service, what was left over carried into an empty preparation, and the whole
preparation checked anew by one change. From the root of the monorepo:

```sh
composer bench
```
