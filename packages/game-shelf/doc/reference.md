# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Shelves

| Type | Role |
|---|---|
| `GameShelves` | The service: copies, wishes and their order, notes, visibility, import and the games members can bring. |
| `Shelf` | A member's shelf: its copies, its wishes in the member's order, its notes, its visibility and its version. |
| `Copy` | A box: its game, its statuses, its edition, its location, whether it can be brought and whether an import placed it. |
| `CopyStatus` | Owned, previously owned, preordered or for trade. |
| `Wish` | A game wished, its kinds and its level. |
| `WishKind` | Wishlist, want to buy, want in trade or want to play. |
| `WishLevel` | From "must have" to "don't buy this". |
| `Note` | A member's rating, comment, tags and plays of a game. |
| `Visibility` | Private, members or public. |
| `Viewer` | The member, another member, or anyone. |
| `ShelfView` | What a viewer sees of a shelf. |
| `BringableGame` | A game a member can bring to a game night. |
| `ShelfImport` | What an import changed. |
| `ImportMode` | Whether an import adds only, or synchronizes the imported copies with the file. |

## Export

| Type | Role |
|---|---|
| `Export\ShelfCsv` | Writes a view as CSV: the copies, the wishlist and the notes. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\ShelfStore` | Keeps the shelves at a version. |
| `Contract\ShelfObserver` | Told of the events of a shelf. |
| `Store\InMemoryShelfStore` | The shelves in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\ShelfEvent` | Every event: the member and the game. |
| `Event\CopyAdded` | A copy was placed. |
| `Event\CopyRemoved` | A copy was taken off. |
| `Event\CopyOfferedForTrade` | A copy became for trade. |
| `Event\CopyWithdrawnFromTrade` | A copy is no longer for trade. |
| `Event\GameWished` | A game joined the wishlist. |
| `Event\WishWithdrawn` | A game left the wishlist. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\ShelfException` | Every exception of the library. |
| `Exception\InvalidShelf` | A value out of its shape or its bounds. |
| `Exception\ShelfRefused` | A change the shelf does not allow. |
| `Exception\ShelfConflict` | The shelf changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of the identifiers. Internal. |
| `Internal\Text` | The shape of the texts a member writes. Internal. |
