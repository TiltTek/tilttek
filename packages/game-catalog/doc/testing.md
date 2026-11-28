# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network:

- the values and their refusals: every field of an entry at its bounds and one past them, identifiers of each
  database, barcodes of each length with their check digits, origins, errata, links, ballots and corrections;
- the entry: corrections kept beside the values of the source, fields returned to it, identifiers and errata;
- the services: synchronization from a source, feeding up to a limit, links up to their limit, barcodes identified
  once then locally, corrections accepted, refused, in conflict and forced, votes counted, experiences frozen;
- the store in memory: versions, indexes, links, corrections, ballots and history;
- the collection reader: an export with quotes and commas, headers without case, every refusal of a file and of a
  row, the limits reached exactly and passed; and the importer.

The integration suite, under `tests/Integration`, wires the services on one store, as an application does: a source
feeds the catalogue, an entry is added by hand and linked, members vote and correct, the source sends its entries
again, a member imports their export, and a box is scanned twice.

In the monorepo, Infection holds the library at 100 % of covered mutants.

## Fuzzing

The collection reader has a fuzz target, `collection-file`, fed by the seeds of
`tests/Fuzz/corpus/collection-file`: whatever the file, the reader returns its rows within its limits, or refuses it
with an exception of the library; every row holds an identifier of its database, statuses without repeats, and a
priority and plays within their bounds.

```sh
composer fuzz -- collection-file
```

## Complexity

With R rows of C cells, the reader is O(R · C) and holds the file once in memory, bounded by `Limits::bytes`.
Synchronizing an entry is O(I + E) for its I identifiers and E errata — at most 3 and 500 —, counting the ballots of an
entry is O(B) for its B ballots of at most 30 numbers of players each, and linking reads the links of both entries,
at most 200 each.

## Testing code that uses the catalogue

Use `Store\InMemoryCatalogStore` and a fixed clock:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;

$catalog = new Catalog(new InMemoryCatalogStore(), new MockClock('2027-04-01 09:00 UTC'));
$catalog->add('tide', new Details('Tide'));

self::assertSame('Tide', $catalog->sheet('tide')->details()->name);
```
