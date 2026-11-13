# Changelog

All notable changes to `tilttek/deck-builder` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Format`: the declared rules of a format — the smallest deck, 0 to 100 000 cards, the largest or none, the copies of
  each card, 1 to 999, up to 10 000 banned and 10 000 restricted cards, and up to 100 dated taboo lists —, read from
  and written to arrays and JSON of at most 1 MiB, every fault an `InvalidDeck`.
- `TabooList`: cards banned or restricted from a day on, the latest applying on a day replacing those before it and
  adding to the format's own lists, the day taken in the time zone of the moment given.
- `Ruling`: the rules of a format on a day — what bans a card, what restricts it, and its copies allowed, a restriction
  never raising a limit.
- `Card`: a card as the application's catalog gives it, with its own number of copies when it differs from the format's.
- `Deck` and `DeckReference`: up to 1 000 distinct cards of 1 to 999 copies in the order they were added, changed by new
  decks, trimmed to a collection, copied with a link to the version of the original, read from and written to arrays
  and JSON.
- `DeckValidator`, `Validation`, `Violation` and `ViolationType`: a deck checked against its format on a given day, and
  against a collection, every violation returned with a sentence people read and the values it was built from.
- `Decks`, `DeckVersion` and `DeckDiff`: versions saved from 1 and never rewritten, an unchanged deck keeping its latest
  version, a version saved twice at once refused with `DeckConflict`, the history, the differences between two versions,
  and copies as the first version of a new deck linked to the original.
- `Contract\CardCatalog`, `Contract\FormatCatalog`, `Contract\OwnedCards`, `Contract\DeckStore`,
  `Catalog\InMemoryCardCatalog`, `Catalog\InMemoryFormatCatalog`, `Catalog\OwnedCardList` and
  `Store\InMemoryDeckStore`: the ports to the application's cards, formats, collections and versions, and their
  adapters in memory.
- `Identifiers`: the shapes of codes, identifiers and names.
- No publisher's catalogue is bundled or imported: the application provides its own cards and formats, as
  `doc/catalogues-and-licences.md` says.
- The fuzz targets of a format and a deck, and the benchmarks of the largest deck.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
