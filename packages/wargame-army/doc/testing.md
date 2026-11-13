# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: the shapes of codes, versions and names,
every bound of a catalogue and a list, catalogues and lists written and read back with everything else refused, points
with models and options, every rule of the validator and its message, counts and points shares at their bounds, the
application's own constraints, and the text and the escaped HTML of the export. In the monorepo, Infection holds the
library at 100 % of covered mutants. The catalogue of the tests is invented.

`tests/Fuzz/army-catalogue.php` and `tests/Fuzz/army-list.php` are the fuzz targets of a catalogue and a list: any text
reads as a catalogue or a list that writes back to itself, or is refused with its exception, never another one; the
list is then checked and exported. `composer fuzz` runs them.

`tests/Benchmark/WargameArmyBench.php` measures the largest list — 20 detachments of 200 units with ten options each —
validated against a catalogue of 2 000 units and 200 constraints, its HTML export, and the catalogue read from JSON.

## Testing an application

Give the validator an in-memory provider of an invented catalogue:

```php
$validator = new ArmyValidator(new InMemoryCatalogueProvider(Catalogue::fromJson($inventedCatalogue)));
```
