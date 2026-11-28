# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/game-catalog` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Details`: what an entry says of a game as its publisher states it — name, `EntryType`, `GameKind`, game system,
  publishers, designers, year, numbers of players, playing time, minimum age, languages as BCP 47 tags, levels of a
  scenario, `LanguageDependence`, description and cover —, each field within its bounds, compared and merged by `Field`.
- `Entry` and `Origin`: an entry entered by hand or sent by a source under its key, with the values of its source and,
  beside them, the fields the administration corrected, which the source sending the entry again leaves in place; at
  most 500 errata, and hidden or shown.
- `ExternalId` and `IdentifierScheme`: the identifier of a game in BoardGameGeek, RPGGeek or Wikidata, and the link to
  its page; nothing else of those databases is kept.
- `Gtin`: the barcode of a box, of 8, 12, 13 or 14 digits, its check digit verified, kept as 14 digits.
- `Erratum`, `Link` and `LinkType`: errata in the order they were published, and links between entries — the
  relationship types of OpenTabletop, with `edition_of` and `translation_of`.
- `Catalog`: entries added by hand and synchronized from the sources that feed the catalogue, with `feed()` bounded;
  identifiers held by one entry each; errata published with the observer told; links, at most 200 per entry; barcodes
  given to an entry, and `identify()`, which finds a scanned box locally, else through a `Contract\BarcodeResolver`
  once; hiding; the page of a game as a `Sheet`; the history as `Change` lines.
- `Corrections`: a correction proposed on any field, at most 20 waiting per member; accepted with `CorrectionConflict`
  when a field it changes was changed on the entry since, unless forced; refused with a note; a corrected field
  returned to the value of its source.
- `Ballot`, `Polls`, `PollResults` and `PlayerCountResult`: one ballot per member and entry, with a rating of 1 to 5
  and one of the three `PlayerCountChoice` for each number of players, the weight, the minimum age and the dependence
  on language; counted in whole hundredths — the numbers of players rated 4.00 and 3.00 or more, those chosen best or
  recommended by most members, the average weight, the median age, the dependence most voted.
- `Reviews`: the games played and run by the author of a review, read from a `Contract\PlayHistory` and frozen in the
  `Contract\ReviewSystem` of the application.
- `Import\CollectionReader`, `Import\Columns` and `Import\Limits`: a collection file in CSV read within bounds of
  size, rows, columns and cells, the columns of an export of BoardGameGeek or RPGGeek given by
  `Columns::geekExport()`, a row that cannot be read reported with its line.
- `Import\CollectionImporter`: the games of the file found in the catalogue by identifier, the role-playing games
  unless other kinds are given, the identifiers the catalogue does not hold returned for the member.
- `Contract\CatalogStore` and `Store\InMemoryCatalogStore` for tests; `Contract\EntrySource`,
  `Contract\BarcodeResolver`, `Contract\CatalogObserver`, `Contract\PlayHistory` and `Contract\ReviewSystem`.
- The documentation in `doc/`, one file per topic, indexed by the README; unit and integration tests, and a fuzz
  target of the collection reader.
- `composer.json`, `phil-dev.php`, `composer-dependency-analyser.php`, `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
