# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the shelves | Implement `Contract\ShelfStore`, and pass it to `GameShelves`. |
| Act on what happened | Implement `Contract\ShelfObserver`, and pass it to `GameShelves`. |
| Give a game night the games the members can bring | Adapt `GameShelves::bringable()` to the game night's port. |

## The store

`Contract\ShelfStore` keeps one shelf per member, at a version: `shelf()` reads it, and gives an empty shelf at
version 0 when none is kept; `save()` keeps it at the next version and returns it. A shelf at version 0 is new, and
the store has no shelf for its member; any other is kept only when the store holds it at that version, and
`ShelfConflict` is raised otherwise, nothing kept. A shelf holds its copies, its wishes in their order, its notes and
its visibility: one write keeps them together.

`Store\InMemoryShelfStore` keeps the shelves in memory, as long as the object lasts: it is for tests. An application
keeps them in its database; every value holds strings, numbers, booleans and enumerations only, and the order of the
wishes is the order of `Shelf::wishes()`. A store keeps the `imported` mark of each copy: a copy read back without it
is a copy entered by hand, which a synchronized import never touches.

## The observer and its events

`Contract\ShelfObserver::notify()` is told each fact once the shelf is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\CopyAdded` | A copy was placed, or given another game. | the copy |
| `Event\CopyRemoved` | A copy was taken off, or given another game. | the copy |
| `Event\CopyOfferedForTrade` | A copy became for trade. | the copy |
| `Event\CopyWithdrawnFromTrade` | A copy for trade no longer is, or left the shelf. | the copy |
| `Event\GameWished` | A game joined the wishlist. | — |
| `Event\WishWithdrawn` | A game left the wishlist. | — |

Every event implements `Event\ShelfEvent`, whose `member` and `game` are identifiers. An application sends a message
from them — through Messenger, with the identifiers alone — and reads the rest from the shelf.

The facts are told after the store kept the shelf. When the observer fails on a fact, it is still told the others,
then the first exception it raised is raised again by the method that made the change; the shelf stays kept. An
application that must never lose a fact keeps the shelf and the messages of its observer in one transaction — a
Messenger transport on the same database, for instance — so that a failed fact undoes the change. An event is told
whatever the visibility of the shelf: a trade service or a stock reminder acts on it only as far as the member shows
the shelf or agreed to be told, and never makes a private wishlist public.

## A game night's shelves

`GameShelves::bringable()` gives the games members can bring to a game night as `BringableGame`, with the game and
its owner, once per member and game, up to a limit. A game night library that reads the members' games through a port
of its own receives them through an adapter the application writes; the package depends on no such library.

For `tilttek/game-night`, whose `Contract\ShelfReader` gives `Choice\OwnedGame` values:

```php
use Override;
use Tilttek\GameNight\Choice\OwnedGame;
use Tilttek\GameNight\Contract\ShelfReader;
use Tilttek\GameShelf\GameShelves;

final readonly class MemberShelves implements ShelfReader
{
    public function __construct(private GameShelves $shelves)
    {
    }

    #[Override]
    public function games(array $members, int $limit): iterable
    {
        foreach ($this->shelves->bringable($members, $limit) as $game) {
            yield new OwnedGame($game->game, $game->owner);
        }
    }
}
```

Both libraries take the identifiers of the catalogue for the games, and members without "@": the values pass as they
are. `bringable()` reads at most 2000 members and gives at most 10 000 games at once, more than a game night asks for.
A copy that is not owned, or whose member keeps it at home with `bringable: false`, is never given.

## Worker-safe

The service and everything it uses keep nothing between calls: they can live as long as a worker, and serve several
sites of one application at once, the store deciding which shelves it reads.
