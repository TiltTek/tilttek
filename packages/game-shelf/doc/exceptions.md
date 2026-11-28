# Exceptions

Every exception of the library implements `Exception\ShelfException`: catch it to catch them all.

| Exception | Raised when |
|---|---|
| `Exception\InvalidShelf` | A value is out of its shape or its bounds: an identifier, a text, a rating, statuses that do not go together, a list too long. |
| `Exception\ShelfRefused` | The shelf does not allow the change: the copy or the wish is not on it, a wish moves beside itself, the shelf is full, or the game is hidden in the catalogue. |
| `Exception\ShelfConflict` | The shelf changed since it was read: read it again and make the change anew. |

A game the catalogue does not hold raises the `EntryNotFound` of `tilttek/game-catalog`. Nothing is kept when an
exception is raised.
