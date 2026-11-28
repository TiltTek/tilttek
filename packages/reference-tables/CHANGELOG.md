# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/reference-tables` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `ReferenceTable` and `Lookup`: a table of a game system with 1 to 32 columns and 1 to 1,000 rows, found by key or by
  range, its name and description by locale, its source and licence as a `Provenance` of `tilttek/rpg-systems`, its
  system, category and page; a die of one instruction of `tilttek/dice` on a table looked up by range, kept in its
  canonical notation; keys once, ranges without overlap.
- `ReferenceTable::row()`, `hasRow()`, `lookup()`, `position()`, `bounds()` and `gaps()`: a row by its key, the row whose
  range covers a value by halving the sorted ranges, its position, the values the table covers and those it leaves out.
- `Column`, `ColumnKind` and `Cell`: columns of texts by locale, integers up to a billion either way, or dice
  expressions kept in the canonical notation of `tilttek/dice`, each cell checked against its column.
- `Row`: a key or a range, never both, and cells by column, any of them left out.
- `Range`: `12`, `3–5`, `21+`, `≤0`, and `96–00` on a d100 where `00` stands for 100, within ±1,000,000, read from up to
  32 bytes and printed back.
- `TableCatalogue`: up to 2,000 tables, each key once, by system and category, with the categories in order and the
  sources of every table, or of those a page shows, each once for the attribution.
- `Rolling\TableRoller` and `Rolling\TableRoll`: a table rolled with its die through `tilttek/dice` on the caller's
  `Randomizer`, with a modifier, a total beyond the table clamped to its first or last row when asked, a total in a gap
  refused; a die rolled at the table entered; the dice of a cell rolled.
- `Contract\TableObserver` and `Event\TableRolled`: each roll told by the table's key, the row's position, the total and
  whether it was entered.
- `Search\TableSearch` and `Search\SearchHit`: a query of up to 100 characters found in the names, descriptions, row keys
  and text cells of a catalogue, in a locale, without regard to case or to the accents of Latin letters, for up to 100
  hits, in one system when asked.
- `Format\TableReader` and `Format\Syntax`: documents of the format `tilttek-reference-tables/1` in YAML or JSON, up to
  2 MiB, with a default locale, system and source, every unknown field refused and every refusal at its place.
- The exceptions `InvalidTable`, `InvalidDocument`, `UnknownTable`, `UnknownRow` and `LookupRefused`, under
  `ReferenceTablesException`.
- The example document `examples/lanterns.yaml`, the fuzz targets of the documents and of the ranges, and the
  benchmarks of the lookups, rolls, searches and reading.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
