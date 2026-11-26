# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Entry` | Its own entries, built in code | [Reference](reference.md#entries) |
| `EntryReader` | Its own rows, from configuration, a file or a database, in the shipped data's form | [Data](data.md#adding-an-entry) |
| `Catalog::shipped($zone, $own)`, `Catalog::with()` | The shipped entries and its own in one catalog | [Reference](reference.md#the-catalogs-questions) |
| `new Catalog($entries, $zone)` | Only the entries it chooses: some of `ShippedData::entries()`, or none | [Reference](reference.md#the-catalogs-questions) |
| `Phil\Almanac\Rule\Rule` | A rule of its own, implemented for `phil/almanac`, given to an `Entry` | [Reference](reference.md#entries) |
| `DataCheck` | The check of its own rows before it reads them | [Data](data.md#adding-an-entry) |

The entries, filters and occurrences are values; the catalog keeps no state between two questions.
