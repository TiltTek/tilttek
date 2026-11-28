# Exceptions

Every exception of the library implements `Tilttek\GameNight\Exception\GameNightException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidNight` | A value of a night, a table, a sign-up, a vote, a choice or a play is out of its shape or its bounds | `A table has 1 to 30 seats.` |
| `NightRefused` | The night does not allow the change now: cancelled, not published, started or full, the member not signed up, the table without the free seats | `The night has started.` |
| `NightConflict` | The night changed, or was added, since it was read: nothing is kept | `The game night night-42 changed since it was read.` |
| `NightNotFound` | No night has the identifier | `No game night has the identifier night-9.` |

`GameNights::propose()` and `NightPlays::record()` also let the `EntryNotFound` of `tilttek/game-catalog` through,
when a game is not in the catalogue.
