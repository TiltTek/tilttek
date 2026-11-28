# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the nights | Implement `Contract\NightStore`, and pass it to `GameNights` and `NightPlays`. |
| Tell members what happened | Implement `Contract\NightObserver`, and pass it to `GameNights`. |
| Read the games the members can bring | Implement `Contract\ShelfReader`, and pass it to `Choice\GameChooser`. |
| Keep the games played | Implement `Contract\PlayJournal`, and pass it to `NightPlays`. |

## The store

`Contract\NightStore` keeps the nights, each at a version: `night()` reads one, `save()` keeps it at the next version
and returns it. A night at version 0 is new, and the store has no night of its identifier; any other is kept only when
the store holds it at that version, and `NightConflict` is raised otherwise, nothing kept. A night holds its tables,
its sign-ups, its games proposed and its votes: one write keeps them together.

`Store\InMemoryNightStore` keeps the nights in memory, as long as the object lasts: it is for tests. An application
keeps them in its database; every value holds strings, numbers, booleans, enumerations and dates only.

## The observer and its events

`Contract\NightObserver::notify()` is told each fact once the night is kept, by identifiers only:

| Event | When |
|---|---|
| `Event\NightPublished` | The night opened to sign-ups. |
| `Event\NightFull` | The last free seat was taken. |
| `Event\NightCancelled` | The night was cancelled. |
| `Event\SeatOffered` | A member waiting holds their places; it carries the member. |

Every event implements `Event\NightEvent`, whose `night` is the identifier of the night. An application sends a
message or a notification from them — through Messenger, with the identifiers alone — and reads the rest from the
night. No event carries an address or a name, and none is repeated.

## The shelves

`Contract\ShelfReader::games()` gives the games the members named can bring, as `Choice\OwnedGame`: the identifier of
the game in the catalogue and its owner. It gives at most the limit it is asked for; `Choice\GameChooser` reads no
more than 1000 games.

## The play log

`Contract\PlayJournal::record()` receives a `PlayedGame`, once its night, table, players and guests were checked.

## Worker-safe

The services and everything they use keep nothing between calls: they can live as long as a worker, and serve several
sites of one application at once, the store deciding which nights it reads.
