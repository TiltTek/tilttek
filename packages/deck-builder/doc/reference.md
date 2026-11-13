# Reference

| Type | Role |
|---|---|
| `Card` | A card as the card catalog gives it: identifier, name and its own number of copies |
| `Format` | The declared rules of a format: sizes, copies, banned and restricted cards, taboo lists; read from and written to arrays and JSON |
| `TabooList` | Cards banned or restricted from a day on |
| `Ruling` | The rules of a format on a day: what bans or restricts a card, and its copies allowed |
| `Deck` | A deck: its cards in order, its format and its original; changes by new decks; read from and written to arrays and JSON |
| `DeckReference` | A version of a deck, the original a copy links to |
| `DeckValidator` | Checks a deck against its format on a day, and against a collection |
| `Validation` | Every violation of a deck, with the ruling and the day |
| `Violation` | A rule a deck breaks, with its message and values |
| `ViolationType` | The kinds of violations |
| `Decks` | Saves versions, gives the history and the differences, copies decks |
| `DeckVersion` | A saved version of a deck, with its moment in UTC and its note |
| `DeckDiff` | The differences between two decks |
| `Identifiers` | The shapes of codes, identifiers and names |
| `Contract\CardCatalog`, `Contract\FormatCatalog` | Where the application keeps its cards and formats |
| `Contract\OwnedCards` | The copies of each card a collection owns |
| `Contract\DeckStore` | Where the versions of the decks are kept |
| `Catalog\InMemoryCardCatalog`, `Catalog\InMemoryFormatCatalog`, `Catalog\OwnedCardList` | The catalogs and a collection in memory |
| `Store\InMemoryDeckStore` | The versions of the decks in memory |
| `Exception\DeckBuilderException` | The interface of every exception of the library |
| `Exception\InvalidDeck`, `UnknownFormat`, `UnknownDeck`, `DeckConflict` | See [Exceptions](exceptions.md) |

`ArrayInput` is internal.
