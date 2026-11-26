# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: entries and their refusals, the reader of rows, the
catalog — a year, a period, a day read in the catalog's time zone, filters, an application's own entries, identifiers
given twice, the range of years —, and the shipped data. The data test checks every row with `DataCheck`, computes
every entry once a year from 2023 to 2030 and in 2100, leap years included, pins known dates — `day 256` on
September 12 in a leap year —, and shows that a fifth Saturday of February gives a date only in a year that has one.
In the monorepo, Infection holds the package at 100 % of covered mutants.

## Benchmarks

`tests/Benchmark/CatalogBench.php` measures building the shipped catalog, the dates of a day and those of a year. On
PHP 8.6 without an opcode cache: building 0.17 ms, a day 0.15 ms, a year 0.34 ms.

## Testing code that shows dates

Build a catalog of a few entries in a fixed time zone and ask for a known day:

```php
$catalog = new Catalog([
    new Entry('towel-day', ['en' => 'Towel Day', 'fr' => 'Journée de la serviette'], RuleParser::parse('05-25'),
        'https://example.org/towel', 'readers', new DateTimeImmutable('2026-11-21')),
], new DateTimeZone('UTC'));

$catalog->on(new DateTimeImmutable('2026-05-25')); // one Occurrence
```
