# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: entries and their refusals, the reader of rows, the
catalog — a year, a period, a day read in the catalog's time zone, filters, an application's own entries, identifiers
given twice, the range of years —, the days of prompts — their index, across a change of time and in another time
zone —, and the shipped data. The data test checks every row with `DataCheck`, computes every entry once a year from
2023 to 2030 and in 2100, leap years included, pins known dates — RPGaDAY from August 1 to 31 —, and shows that a
fifth Saturday of February gives a date only in a year that has one. In the monorepo, Infection holds the package at
100 % of covered mutants.

## Benchmarks

`tests/Benchmark/CatalogBench.php` measures building the shipped catalog, the dates of a day and those of a year. On
PHP 8.6 without an opcode cache: building 0.05 ms, a day 0.03 ms, a year 0.06 ms.

## Testing code that shows events

Build a catalog of a few events in a fixed time zone and ask for a known day:

```php
$catalog = new Catalog([
    new Entry('prompt-month', ['en' => 'Prompt month', 'fr' => 'Mois d’invites'], RuleParser::parse('08-01'),
        'https://example.org/prompts', 'players', new DateTimeImmutable('2026-11-21'), days: 31, prompts: true),
], new DateTimeZone('UTC'));

$catalog->promptDays(new DateTimeImmutable('2026-08-12'))[0]->index; // 12
```
