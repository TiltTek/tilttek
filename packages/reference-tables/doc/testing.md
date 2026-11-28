# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: ranges read, printed and refused; tables built and refused
rule by rule, at their limits of 32 columns and 1,000 rows; lookups by key and by range in any order of rows, bounds and
gaps; cells of each kind and their wrong reads; the catalogue by system and category, with each source once and the
attribution of `tilttek/rpg-systems`; rolls on a seeded `Randomizer`, modifiers, clamping, dice entered, cells rolled
and the events told; searches across locales, case and accents, and their bounds; the reader with the example of
`examples/`, YAML and JSON alike, and every refusal at its place. In the monorepo, Infection holds the library at 100 %
of covered mutants.

`tests/Fuzz/document.php` is the fuzz target of the reader: any YAML text is refused with the library's exceptions or
gives tables whose every row is found again by its key or by its range. `tests/Fuzz/range.php` is the fuzz target of
the ranges: any text is refused or gives a range whose printed form reads back as the same range. `composer fuzz` runs
them.

`tests/Benchmark/LookupBench.php` measures a lookup by range in a table of 1,000 rows, a lookup by key, a roll, a search
of 50 tables of 100 rows, and reading those tables from YAML.

## Testing code that reads tables

Read a small document of your own, and roll with a seeded `Randomizer`, so that the same test gives the same row:

```php
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\ReferenceTables\Rolling\TableRoller;

$tables = new TableReader()->catalogue([
    'format' => TableReader::FORMAT,
    'source' => ['title' => 'Test', 'licence' => 'owned'],
    'tables' => [[
        'key' => 'coin',
        'names' => 'Coin',
        'die' => '1d2',
        'columns' => [['key' => 'side', 'names' => 'Side']],
        'rows' => [['range' => '1', 'side' => 'Heads'], ['range' => '2', 'side' => 'Tails']],
    ]],
]);

$roll = new TableRoller()->roll($tables->get('coin'), new Randomizer(new Mt19937(42)));
```

To test a row without dice, call `TableRoller::enter()` with the value you want. A `Contract\TableObserver` of your own,
kept in the test, collects the events.
