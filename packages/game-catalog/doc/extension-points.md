# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the catalogue | Implement `Contract\CatalogStore`, and pass it to the services. |
| Feed the catalogue | Implement `Contract\EntrySource`, and pass it to `Catalog::feed()`. |
| Identify a box the catalogue does not know | Implement `Contract\BarcodeResolver`, and pass it to `Catalog`. |
| Tell members of errata and corrections | Implement `Contract\CatalogObserver`, and pass it to `Catalog` and `Corrections`. |
| Show the experience of a reviewer | Implement `Contract\PlayHistory` and `Contract\ReviewSystem`, and pass them to `Reviews`. |
| Read another collection file | Give `Import\Columns` its headers, and `Import\Limits` its bounds. |

## The store

`Contract\CatalogStore` keeps the entries, each at a version: `entry()` reads one, `save()` keeps it at the next
version and returns it. An entry at version 0 is new, and the store has no entry of its identifier; any other is kept
only when the store holds it at that version, and `CatalogConflict` is raised otherwise, nothing kept. `save()` also
indexes the identifiers and the origin of the entry, and raises `CatalogRefused` when another entry holds one, nothing
kept. Corrections are kept at a version the same way. The store also keeps the barcodes, the links by key, the
ballots by entry and member, and the history, the latest first.

`Store\InMemoryCatalogStore` keeps everything in memory, as long as the object lasts: it is for tests. An application
keeps the catalogue in its database; every value holds strings, numbers, enumerations and dates only.

## The sources

`Contract\EntrySource` gives its name and its entries. `Catalog::feed()` reads them up to its limit, and synchronizes
each one, as [the entries and sources](catalogue.md#sent-by-a-source) explain. A source written on a third-party
service is an adapter of the application, and follows the terms of that service: the library keeps what the source
sends, and a source of BoardGameGeek or RPGGeek sends the identifier alone.

## The barcode resolver

`Contract\BarcodeResolver::resolve()` returns the identifier of a game in an open database for a barcode, or `null`.
It is asked once per barcode: `Catalog::identify()` keeps the answer when an entry holds the identifier.

## The observer

`Contract\CatalogObserver` is told, by identifiers only, of an erratum published, a correction proposed and a
correction decided. An application sends a notification or a message from it, and reads the rest from the
catalogue, so that a message carries no personal data.

## The plays and the reviews

`Contract\PlayHistory` counts the games of an entry a member played and ran, from the plays the application records.
`Contract\ReviewSystem` keeps the `Experience` of a review beside it, in the application's review system, and gives
it back.

## Worker-safe

The services and everything they use keep nothing between calls: they can live as long as a worker, and serve several
sites of one application at once, the store deciding which catalogue it reads.
