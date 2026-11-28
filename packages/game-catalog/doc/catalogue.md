# Entries and sources

## An entry

An `Entry` is one game: one product, so that an expansion, a scenario or a translation is an entry of its own,
linked to the others. It has an identifier the application chooses — opaque, starting with a letter, at most 128
characters —, an `Origin`, and `Details`:

| Field | Value | Bounds |
|---|---|---|
| `name` | The name of the game | 1 to 200 characters, one line |
| `type` | `EntryType`: base game, expansion, standalone expansion, promo, accessory, fan expansion, scenario, supplement | — |
| `kind` | `GameKind`: board, card, role-playing, miniatures, larp | — |
| `system` | The key of the game system of a role-playing game, from the application's list of systems | An identifier |
| `publishers`, `designers` | Names, in the order of the box | At most 20 each, 1 to 120 characters, each once |
| `year` | The year of publication | 1 to 9999 |
| `players` | The numbers of players the publisher states, as a `Range` | 1 to 999 |
| `playtime` | The minutes of a game the publisher states | 1 to 100 000 |
| `minimumAge` | The age the publisher states | 0 to 99 |
| `languages` | The languages of this edition, as BCP 47 tags | At most 50, each once |
| `levels` | The levels of the characters a scenario is written for | 0 to 1000 |
| `languageDependence` | How much text is read while playing | — |
| `description` | A short description | 1 to 4000 characters, line breaks kept |
| `cover` | The identifier of the cover image, which the application keeps | An identifier |

Text is UTF-8, trimmed, and holds no control character but the line breaks of a description; a value out of its
bounds raises `InvalidEntry`. These are the values of the publisher. The votes of the members — the best numbers of
players, the weight, the age, the dependence on language — are kept apart, in [the ballots](polls.md), and shown
beside them.

`Entry::details()` gives the values to show: those of the source, with [the corrected fields](corrections.md) in
place.

## Entered by hand

```php
$catalog->add('tide', new Details('Tide', players: new Range(2, 4)), [ExternalId::boardGameGeek(1234)], by: 'admin-1');
```

An entry of the same identifier raises `CatalogConflict`; an identifier another entry holds raises `CatalogRefused`.

## Sent by a source

A source is a port that feeds the catalogue — the published books of an authoring tool, a list the site keeps, an
open database whose licence allows it. It implements `Contract\EntrySource`: a name, and the entries it sends, each a
`SourcedEntry` under its own key.

```php
$catalog->feed($books);              // reads at most Catalog::MAX_SYNCHRONIZED entries
$catalog->synchronize($sourced);     // one entry
```

The first time a source sends a key, the catalogue adds the entry under the identifier the `SourcedEntry` gives. The
next times, it finds the entry by its origin and updates it: the values of the source are replaced, the identifiers
and the errata it did not have are added, and the fields the administration corrected stay. An entry sent again
unchanged is not saved again. A source that sends an entry under another name than its own raises `CatalogRefused`.

A source feeds the catalogue and is never its only copy: every entry it sent stays, and the catalogue keeps working
when the source is gone or stops answering. The library calls no third-party service by itself.

## Identifiers of open databases

An `ExternalId` is the identifier of the game in a database of `IdentifierScheme`:

| Scheme | Value | Link |
|---|---|---|
| `BoardGameGeek` | Digits | `https://boardgamegeek.com/boardgame/<id>` |
| `RpgGeek` | Digits | `https://rpggeek.com/rpgitem/<id>` |
| `Wikidata` | `Q` and digits | `https://www.wikidata.org/wiki/<id>` |

Of BoardGameGeek and RPGGeek, the catalogue keeps the identifier and builds the link, and nothing else: the use of
their data is under their own terms. Wikidata publishes its data under CC0, which makes its identifier a free anchor
of an entry. An entry has at most one identifier of each database, and an identifier belongs to one entry:
`Catalog::addIdentifier()` adds one, `Catalog::findByIdentifier()` finds the entry.

## Errata

An `Erratum` has an identifier, where it applies — a page, a card, a rule —, its text and its date of publication.
`Catalog::publishErratum()` adds it to the entry, at the end; an entry keeps at most 500. The observer is told of each
erratum published, by `publishErratum()` or by a source sending a new one: the moment to tell the members who follow
the game.

## Links

A `Link` relates one entry to another with a `LinkType`, and an optional ordinal — the release order of the
expansions, for instance. `Catalog::link()` keeps it, or changes its ordinal; both entries are in the catalogue, an
entry is never linked to itself, and an entry has at most 200 links. A link `integrates_with` reads the same from
both entries. The types are in [the vocabulary](vocabulary.md).

## Barcodes

A `Gtin` is the barcode of a box: an EAN-13, a UPC-A, an EAN-8, a GTIN-14 or the ISBN-13 of a book, its check digit
verified, kept as 14 digits so that the same box read as a UPC or as an EAN is the same code.
`Catalog::assignBarcode()` gives it to an entry; a barcode is given to one entry at most.

`Catalog::identify()` finds the entry of a scanned box: the barcode already given to an entry, else the identifier a
`Contract\BarcodeResolver` finds for it, when an entry holds that identifier. The barcode is then given to the entry,
so that the resolver is asked once per barcode, and the next box of the same edition is identified locally — an
inventory of three boxes of one edition asks once. The number of copies stays with the inventory, never in the
catalogue.

## Hiding

`Catalog::hide()` hides an entry and `Catalog::show()` shows it again. A hidden entry keeps its page and its links;
the application decides where it does not show it, and the collection import treats it as an entry the catalogue
does not hold. A rule of one site — showing by default only the games of some creators, for instance — is a filter
the application writes on its own queries, not an option of the library.

## The page of a game

`Catalog::sheet()` returns a `Sheet`: the entry, `details()` as corrected, the `PollResults` of the members, and its
links, from and to it, with `linksOf()` by type.

## The history

Each change of an entry adds a `Change` to its history: its `ChangeKind`, the fields changed, when, by whom — a
member or the name of a source —, and what it is about — the erratum, the identifier, the barcode, the correction.
`Catalog::history()` gives the last ones, the latest first, 50 by default.

## The experience of a reviewer

`Reviews::experience()` gives the games played and run by the author of a review, read from a
`Contract\PlayHistory` the first time it is asked and kept by the application's `Contract\ReviewSystem`; it is the
same after, whatever the author plays since. The reviews themselves are the application's: the library depends on no
review package.
