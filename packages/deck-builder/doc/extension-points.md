# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\CardCatalog` | Its cards; `Catalog\InMemoryCardCatalog` keeps them in memory | [Catalogues and licences](catalogues-and-licences.md) |
| `Contract\FormatCatalog` | Its formats; `Catalog\InMemoryFormatCatalog` keeps them in memory | [Formats and rules](formats-and-rules.md) |
| `Contract\OwnedCards` | A collection; `Catalog\OwnedCardList` reads one from an array | [Building and validating decks](building-and-validating.md#decks-limited-to-a-collection) |
| `Contract\DeckStore` | The versions of the decks; `Store\InMemoryDeckStore` keeps them in memory | [History and copies](history-and-copies.md) |

Every point is passed to a constructor; `DeckValidator` and `Decks` keep no state between two calls.

## Implementing a store

- `add()` returns false, writing nothing, when the store holds that version of that deck: a unique key on the deck's
  identifier and the version's number gives it.
- `latest()` gives the highest version; `versions()` lists them from the first to the latest.
- A row keeps `DeckVersion::$deck->toJson()`, the version, `savedAt` in UTC and the note; `Deck::fromJson()` reads the
  deck back.
