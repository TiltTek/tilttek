# Import and export

## Importing a collection file

`tilttek/game-catalog` reads a collection file exported from a game database and finds its games in the catalogue:
its `CollectionImporter` gives a `CollectionImport`, whose items are the games of the catalogue, visible, each once,
with their statuses, the priority of a wish and the plays; it imports the role-playing games unless told the kinds.
`GameShelves::import()` puts them on a member's shelf:

```php
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionImporter;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\Columns;

$file = new CollectionReader()->read($contents, Columns::geekExport(IdentifierScheme::BoardGameGeek));
$found = new CollectionImporter($catalogStore)->import($file, [GameKind::Board, GameKind::RolePlaying]);
$result = $shelves->import('member-1', $found);

$result->copiesAdded;     // and copiesUpdated, wishesAdded, wishesUpdated, playsUpdated, unchanged
$result->refused;         // the games left out
$result->shelf;           // the shelf kept
```

Each game of the file is split between a copy and a wish:

- its statuses owned, previously owned, preordered and for trade make **one copy whose identifier is the game's**,
  marked `imported`;
- its statuses wishlist, want to buy, want in trade and want to play make **a wish**, at the level its priority
  gives when the priority is 1 to 5;
- its plays, when the file gives them, become the plays of the member's **note** of the game.

The whole import is kept in one write, and the observer is told what changed, as for any other change.

## Importing again

A member imports again after a game night, or after buying a game. Each game of the file changes only what the file
changed:

- the copy whose identifier is the game's takes the statuses of the file, and keeps its edition, its location and
  whether it can be brought; a copy the member placed under another identifier is never touched, and a copy the file
  no longer names stays, unless the import [synchronizes](#synchronizing);
- the wish takes the kinds of the file, and its level when the file gives a priority; a new wish goes last of the
  member's order, and a wish kept keeps its place;
- the note takes the plays of the file, and keeps its rating, comment and tags; a note left with nothing is dropped.

A game that changes nothing is counted in `unchanged`. A game whose statuses do not go together — owned and
previously owned, or for trade without being owned — is left out and named in `refused`, and so is a game that would
add to a part of the shelf that is full.

## Synchronizing

By default an import adds and updates: a game the member sold stays owned on the shelf until the member changes it.
`ImportMode::Synchronize` makes the file the account of the collection:

```php
use Tilttek\GameShelf\ImportMode;

$result = $shelves->import('member-1', $found, ImportMode::Synchronize);

$result->copiesPreviouslyOwned; // the imported copies moved to previously owned
```

Besides what an import does, each imported copy whose game the file no longer names as a copy — with none of the
statuses owned, previously owned, preordered or for trade — moves to previously owned, and keeps its edition, its
location, whether it can be brought and its mark. Its note and its wish stay; a copy that was for trade is told as
withdrawn from trade. A copy already previously owned is not counted again.

A synchronized import never touches:

- a copy the member entered by hand, whatever its identifier;
- the copy of a game the file names with statuses that do not go together: the game is in `refused`;
- the copy of a game the file names but the import left out, because the catalogue hides it or it is of a kind not
  imported: the identifiers in `unmatched` and `otherKinds` of the `CollectionImport` are looked up in the catalogue,
  one read each.

An import of only some kinds of games moves the copies of the other kinds only when the file no longer names them at
all: a file that still lists them keeps them through `otherKinds`.

## The CSV export

`Export\ShelfCsv` writes a `ShelfView` as CSV, one file per part:

| Method | Columns |
|---|---|
| `copies()` | copy, game, statuses, edition, location, bringable |
| `wishlist()` | rank, game, kinds, level |
| `notes()` | game, rating, plays, tags, comment |

Each file starts with its header; the lines end with CRLF and every cell is quoted, a quote doubled. The statuses and
the kinds are their values separated by spaces, the tags are separated by "; ", and the level is its number from 1 to
5. A cell that starts with "=", "+", "-" or "@" is written after an apostrophe, so that a spreadsheet reads it as
text, never as a formula.

The export writes what the view holds: the member's own view for a member's export, never more. It names the games
by their identifiers in the catalogue; the application adds their names when it needs to.
