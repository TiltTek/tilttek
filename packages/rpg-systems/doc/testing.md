# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: the records and their refusals, the texts by locale and
their fallbacks, the licences, the systems and their lookups, localisations, the reader with every refusal and its
place, the writer and the round trip through YAML and JSON, the attribution as text and HTML, the importer with the
four profiles and a profile of its own, and data packages written, read back and refused — a document changed, out of
the package, another system, version or source, a manifest that cannot be read —, and the installed data packages
listed and loaded, from a list of their own and from Composer's, replaced in a process of its own. The extracts and
documents it reads are the small files of `examples/`, written for the tests. In the monorepo, the published data
packages — the SRD 5.1, the SRD 5.2.1 and Fate Core, kept in its `data/` directory — are read back with the strict
reader, their records counted and their notices compared with the renderer's; a checkout of the library alone skips
those tests. Each data package has tests of its own, which load it through `InstalledDataPackages`. Infection holds
the library at 100 % of covered mutants.

`tests/Fuzz/document.php` is the fuzz target of the reader: any YAML text is refused with the library's exceptions or
gives a system the writer writes and the reader reads back. `composer fuzz` runs it.

`tests/Benchmark/ReaderBench.php` measures reading a system of 1 000 terms from YAML and from JSON, looking a term up,
listing a category, and rendering the attribution of a page.

## Testing code that reads systems

Build a system in code, or read a small document of your own with `SystemReader`, and give it to the code under test:

```php
$system = new SystemReader()->system([
    'format' => SystemReader::FORMAT,
    'system' => ['key' => 'test', 'names' => 'Test'],
    'source' => ['title' => 'Test', 'licence' => 'owned'],
    'attributes' => [['key' => 'grit', 'names' => 'Grit', 'minimum' => 1, 'maximum' => 6]],
]);
```
