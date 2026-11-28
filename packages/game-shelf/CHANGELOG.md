# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/game-shelf` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Shelf`: a member's shelf, its copies, its wishes in the member's order and its private notes, bounded and checked
  whole at each change.
- `Copy` and `CopyStatus`: a box by its own identifier, owned, previously owned, preordered or for trade, with its
  edition, its location and whether it can be brought to a game night.
- `Wish`, `WishKind` and `WishLevel`: a game wished, wanted to buy, wanted in trade or wanted to play, at a level from
  "must have" to "don't buy this", moved before or after another wish.
- The wishlist, by level then in the member's own order.
- `Note`: a member's rating, comment, tags and plays of a game, private to them.
- `Visibility`, `Viewer` and `ShelfView`: the shelf and the wishlist private until the member shows them, the notes and
  the location of a copy never shown to another viewer.
- `GameShelves`: copies, wishes and their order, notes, visibility, and the games of the catalogue checked; it keeps
  no state between calls.
- `GameShelves::import()` and `ShelfImport`: a collection file read by `tilttek/game-catalog` imported as copies,
  wishes and plays, and imported again without touching the copies entered by hand.
- `ImportMode::Synchronize` and `Copy::$imported`: an import that moves the imported copies the file no longer names to
  previously owned, never the copies entered by hand nor those of the games the import left out.
- `GameShelves::bringable()` and `BringableGame`: the games the members can bring to a game night, for a game night's
  port to adapt.
- The events `CopyAdded`, `CopyRemoved`, `CopyOfferedForTrade`, `CopyWithdrawnFromTrade`, `GameWished` and
  `WishWithdrawn`, by identifiers only, told once to a `Contract\ShelfObserver`; a fact the observer fails on does not
  stop the others, and the first exception is raised again once every fact is told.
- `Export\ShelfCsv`: the copies, the wishlist and the notes of a view as CSV, with formula cells written as text.
- `Contract\ShelfStore`, kept at a version, and `Store\InMemoryShelfStore` for tests.
- The exceptions `InvalidShelf`, `ShelfRefused` and `ShelfConflict`, under `ShelfException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
