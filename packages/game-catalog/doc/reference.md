# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## The catalogue

| Type | Role |
|---|---|
| `Catalog` | The service: entries by hand and from sources, identifiers, errata, links, barcodes, hiding, the page of a game and the history. |
| `Entry` | A game of the catalogue: its details from its source, the corrected fields beside them, its identifiers, its errata, hidden or not, and its version. |
| `Details` | What an entry says of a game as its publisher states it. |
| `Field` | A field of the details: what a correction changes and the history names. |
| `EntryType` | What an entry is as a product. |
| `GameKind` | How a game is played. |
| `LanguageDependence` | How much text a player reads while playing, at five levels. |
| `Range` | A range of whole numbers, both ends included. |
| `Origin` | Entered by hand, or sent by a source under its key. |
| `SourcedEntry` | An entry as a source sends it. |
| `ExternalId` | The identifier of a game in an open database, and the link to its page. |
| `IdentifierScheme` | BoardGameGeek, RPGGeek or Wikidata. |
| `Gtin` | The barcode of a box, kept as 14 digits. |
| `Erratum` | A correction of the game published by its author or publisher. |
| `Link` | One entry related to another. |
| `LinkType` | How one entry relates to another. |
| `Sheet` | The page of a game: the entry, its votes and its links. |
| `Change` | A line of the history of an entry. |
| `ChangeKind` | What changed an entry. |

## Corrections

| Type | Role |
|---|---|
| `Corrections` | The service: proposes, accepts, refuses corrections and returns fields to their source. |
| `Correction` | A correction a member proposes: the details read, the details proposed, the fields changed and the decision. |
| `CorrectionStatus` | Pending, accepted or refused. |

## Votes and reviews

| Type | Role |
|---|---|
| `Polls` | The service: keeps one ballot per member and entry, and counts them. |
| `Ballot` | A member's votes on an entry. |
| `PlayerCountChoice` | Best, recommended or not recommended. |
| `PollResults` | The ballots of an entry, counted. |
| `PlayerCountResult` | The votes on one number of players. |
| `Reviews` | The service: the experience of the author of a review, frozen. |
| `Experience` | The games played and run by the author of a review when writing it. |

## Collection import

| Type | Role |
|---|---|
| `Import\CollectionReader` | Reads a collection file within its bounds. |
| `Import\Columns` | Which columns of the file are read, by their header. |
| `Import\Limits` | The bounds of a collection file. |
| `Import\CollectionFile` | A collection file, read: its rows and its row errors. |
| `Import\CollectionRow` | A row read: the identifier, the statuses, the priority and the plays. |
| `Import\RowError` | A row left out, with its line and why. |
| `Import\CollectionImporter` | Finds the games of a file in the catalogue. |
| `Import\CollectionImport` | What an import found. |
| `Import\CollectionItem` | A game of a member's collection found in the catalogue. |
| `CollectionStatus` | Where a game stands in a member's collection. |

## Ports and store

| Type | Role |
|---|---|
| `Contract\CatalogStore` | Where the catalogue is kept. |
| `Contract\EntrySource` | A port that feeds the catalogue. |
| `Contract\BarcodeResolver` | A port that identifies a barcode the catalogue does not know. |
| `Contract\CatalogObserver` | Told of errata and corrections, by identifiers. |
| `Contract\PlayHistory` | A port to the plays the application records. |
| `Contract\ReviewSystem` | A port to the reviews the application keeps. |
| `Store\InMemoryCatalogStore` | The catalogue in memory, for tests. |

## Exceptions and internals

| Type | Role |
|---|---|
| `Exception\*` | The exceptions, listed in [Exceptions](exceptions.md). |
| `Internal\Identifier` | Internal: the shape of every identifier. |
| `Internal\Text` | Internal: the text an entry keeps. |
| `Internal\Average` | Internal: averages in whole hundredths. |
