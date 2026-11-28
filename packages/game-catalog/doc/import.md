# Collection import

A member imports their collection from the file they export from another catalogue. The import reads the file,
finds its games in the catalogue by identifier, and returns them for the application to keep on the member's shelf.
Nothing is added to the catalogue from the file, and no API is called: a file only.

```php
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionImporter;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\Columns;

$file = new CollectionReader()->read($upload, Columns::geekExport(IdentifierScheme::RpgGeek));
$import = new CollectionImporter($store)->import($file);   // the role-playing games

$import->items;        // CollectionItem: the entry, its statuses, the priority of a wish, the plays
$import->unmatched;    // the identifiers no visible entry holds, for the member to propose the game
$import->otherKinds;   // the games of other kinds than those imported
$import->file->errors; // the rows left out, with their line and why
```

## The file

CSV after RFC 4180: a header row first, UTF-8 with or without its byte order mark, fields quoted with `"` when they
hold the delimiter, a quote or a line break. `Columns` says which columns are read, by their header, matched without
case and without the spaces around them:

| Column | Read as |
|---|---|
| The identifier | An identifier of the database, required |
| The database, or one database for the file | `boardgamegeek`, `rpggeek` or `wikidata` |
| Each status column | `1`, `yes`, `true` or `x` give the status; `0`, `no`, `false` or empty do not |
| The priority | A wish's priority, 1 to 5 |
| The plays | The games played, 0 to 100 000 |

Every other column is left unread: the name of a game, a rating, a comment. `Columns::geekExport()` gives the columns
of the collection exported from BoardGameGeek or RPGGeek — `objectid`, `own`, `prevowned`, `preordered`, `fortrade`,
`want`, `wanttobuy`, `wanttoplay`, `wishlist`, `wishlistpriority`, `numplays` —, all of one database. The delimiter
is `,`, `;`, a tab or `|`.

## Bounds

The file is uploaded by a member and nobody checked it: `Limits` bounds it.

| Limit | Default | Over it |
|---|---|---|
| `bytes` | 2 MiB | The file is refused |
| `rows` | 20 000 | The file is refused |
| `columns` | 100 | The file is refused when its header has more; a row with more is left out |
| `cellBytes` | 4 KiB | The row is left out |
| `errors` | 100 | Further row errors are counted in `moreErrors`, not kept |

A file that is not UTF-8, has no header, or lacks a required column raises `InvalidCollectionFile`. A row that cannot
be read — an identifier out of its shape, a status that is neither yes nor no, a priority or plays out of bounds, a
game already on an earlier row — is left out with its line, the header being line 1, and the reader goes on.

## Finding the games

`CollectionImporter::import()` finds each row's entry by its identifier. It imports the role-playing games, unless
given other `GameKind`s; an entry hidden or not in the catalogue goes to `unmatched`, an entry of another kind to
`otherKinds`, and an entry already imported from an earlier row — the same game under another identifier — is
counted in `duplicates`.
