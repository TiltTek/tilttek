# tilttek/game-shelf

**Know which games each member owns, trades and wants: copies with their statuses, a wishlist in their own order,
private notes, collection import and CSV export.**

tilttek/game-shelf keeps one shelf per member, on the games of `tilttek/game-catalog`:

- **copies** — each box by its own identifier, so that a member may hold two editions of one game, keep one and offer
  the other for trade. A copy is owned, held before, preordered or for trade, names its edition, says where it is
  kept, and whether it may leave the house for a game night;
- **wishes** — the games a member wishes, wants to buy, wants in trade or wants to play, at a level from "must have"
  to "don't buy this", in an order the member sets by moving a wish before or after another;
- **notes** — what the member keeps for themselves, apart from the catalogue's sheet: a rating, a comment, tags and
  the number of plays.

The shelf and the wishlist are private until the member shows them to the signed-in members or to anyone; the notes
and the location of a copy stay private. A view gives each viewer only what they may see, and the CSV export writes
that view, with cells a spreadsheet would read as formulas written as text.

A collection file read by `tilttek/game-catalog` imports into the shelf: one copy per game owned, the wishes with
their level, the plays; importing again after a game night changes only what the file changed, and never touches the
copies entered by hand. A synchronized import also moves the imported copies the file no longer names to previously
owned.

The facts of a shelf — a copy added or removed, offered for trade or withdrawn, a game wished or no longer wished —
go to an observer, each once, by identifiers only. The games a member can bring to a game night are read in one call,
for a game night's port to adapt.

It is a plain PHP library: a service, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Shelf`, `Copy`, `Wish`, `Note`: the shelf, its copies, its wishlist in the member's order and its private notes | Implemented and tested |
| `GameShelves`: copies, wishes and their order, notes, visibility, import and the games members can bring, with their events | Implemented and tested |
| `ShelfView` and `Export\ShelfCsv`: what each viewer sees, and its CSV export | Implemented and tested |
| `Store\InMemoryShelfStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, the forms and the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Shelves](doc/shelves.md) | Copies and their statuses, wishes and their order, notes, visibility and views. |
| [Import and export](doc/import-export.md) | Importing a collection file, importing it again, synchronizing, and the CSV export. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, and the adapter of a game night's shelves. |
| [Exceptions](doc/exceptions.md) | `ShelfException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Export\ShelfCsv;
use Tilttek\GameShelf\GameShelves;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Viewer;
use Tilttek\GameShelf\Visibility;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishLevel;

$shelves = new GameShelves($store, $catalog, $observer);      // your ShelfStore, the Catalog, your ShelfObserver

$shelves->place('member-1', new Copy('copy-1', 'harbour', edition: 'French'));
$shelves->place('member-1', new Copy('copy-2', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'English'));
$shelves->wish('member-1', new Wish('lanterns', level: WishLevel::MustHave));
$shelves->wish('member-1', new Wish('tide'));
$shelves->moveWishBefore('member-1', 'tide', 'lanterns');      // the member's own order
$shelves->note('member-1', new Note('harbour', rating: 8, plays: 12));
$shelves->share('member-1', Visibility::Members, Visibility::Private);

$view = $shelves->view('member-1', Viewer::Member);            // copies without their location, no wishlist, no notes
$csv = new ShelfCsv()->copies($view);
```

## Requirements

PHP 8.5 or later, with `ext-mbstring` and `tilttek/game-catalog`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
