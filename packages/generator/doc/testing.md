# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the documents and their errors, the entries, their attributions and the catalog, every function and
filter of the templates, the checks of the sandbox and of the names, the budget of a result, the grammars, the maps and
their schema, the draws, the seeds and the limits, the two importers, and loads the examples of the package. In the
monorepo, Infection holds the library at 100 % of covered mutants.

The tests script the randomness with an engine that returns the numbers it is given: `Randomizer::getInt($min, $max)`
then returns `$min` plus each number, which lets a test say which entry each pick takes. A clock that moves by a fixed
step at each reading tests the time limit.

## Fuzzing

`tests/Fuzz/` declares four targets for `phil-dev fuzz` in the monorepo: the YAML documents (`document`), the Markov
lists (`markov`), the Tracery importer (`tracery`) and the Perchance importer (`perchance`). Each importer target holds
that any text is refused with the library's own exceptions or gives a document that the reader and the generator
accept and that draws within its limits; their seeds are in `tests/Fuzz/corpus/`.

```sh
composer fuzz -- tracery perchance --budget=60
```

## Benchmarks

`GeneratorBench` draws 1 and 100 results, unique or not, from lists of 10 and 10 000 entries filtered on a tag, and
builds a generator on those catalogs; a pick from a long list costs the same as from a short one once the list has been
filtered on its tags. From the monorepo root:

```sh
composer bench -- packages/tilttek-generator/tests/Benchmark
```

## Testing code that draws

Pass a seeded `Randomizer`, or draw with a seed, and compare with a second draw of the same seed:

```php
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;

$generator = new Generator($catalog, randomizer: new Randomizer(new Xoshiro256StarStar(42)));
self::assertEquals($generator->draw('inn', new Draw(seed: 'x')), $generator->draw('inn', new Draw(seed: 'x')));
```

Building the `Generator` in a test checks the catalog: a test that builds it on the application's files fails on a
template that does not compile or names a list that does not exist.
