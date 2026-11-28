# Exceptions

Every exception of the library implements `Exception\PlayLogException`: catch it to catch them all.

| Exception | Raised when |
|---|---|
| `Exception\InvalidPlay` | A value is out of its shape or its bounds: an identifier, the place, a score, the minutes, the seats, a winner outside a competitive play, a limit, a period that ends before it starts. |
| `Exception\PlayRefused` | The log does not allow the change: a new play is not at version 0 or has the identifier of a kept one, the member did not record the play they change, a correction changes who recorded it, the member holds no seat they confirm or decline, or the game is hidden in the catalogue. |
| `Exception\PlayNotFound` | No play has the identifier. |
| `Exception\PlayConflict` | The play changed since it was read: read it again and make the change anew. |

A game the catalogue does not hold raises the `EntryNotFound` of `tilttek/game-catalog`. Nothing is kept when an
exception is raised.
