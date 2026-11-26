# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the clocks | Implement `Contract\ClockStore`, and pass it to `TurnClock`. |
| Act on what happened | Implement `Contract\ClockObserver`, and pass it to `TurnClock`. |
| Give the time | Pass a PSR-20 `ClockInterface`. |
| Act when a turn runs out of time | Schedule `TurnClock::check()` at `ClockView::$expiresAt`. |
| Say who may pause, adjust or stop | Check it in the application before calling `TurnClock`. |

## The store

`Contract\ClockStore` keeps the clocks, each at a version:

- `clock()` gives one by its identifier, or null;
- `save()` keeps one at the next version and returns it. One at version 0 is new, and the store has none of its
  identifier; any other is kept only when the store holds it at that version, and `ClockConflict` is raised otherwise,
  nothing kept.

Two presses of one player at once both read version 7: the first save keeps version 8, and the second is refused with
`ClockConflict`; read anew, the player no longer holds the turn.

`Store\InMemoryClockStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps them
in its database. A clock holds strings, integers, booleans, a moment, its time control — two integers and two
enumerations, whose values are their string cases — and its players, each strings, integers and up to 8 vacations of two
moments.

## The observer and its events

`Contract\ClockObserver::notify()` is told each fact once the clock is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\ClockStarted` | A clock was started. | |
| `Event\TurnStarted` | A player's turn started. | the player |
| `Event\TurnEnded` | A player's turn ended. | the player, the milliseconds counted |
| `Event\TimeExpired` | A player's budget ran out. | the player |
| `Event\ClockPaused` | A clock was paused. | |
| `Event\ClockResumed` | A clock runs again. | |
| `Event\VacationDeclared` | A player declared a vacation. | the player |
| `Event\TimeAdjusted` | Time was added or taken away. | the player, the milliseconds |
| `Event\ClockStopped` | A clock was stopped, or every player ran out. | |

Every event implements `Event\ClockEvent`, whose `clock` is an identifier; the events of one player implement
`Event\PlayerEvent`, which adds `player`. When a turn passes, `TimeExpired` comes first when the budget ran out, then
`TurnEnded`, then `TurnStarted` or `ClockStopped`. An application sends a message from them — through Messenger, with the
identifiers alone — and reads the rest from the store: a push of the view to the table, a notice to the player whose turn
starts in a game by correspondence.

## The clock and the checks

`TurnClock` reads the time from the PSR-20 `ClockInterface` it is given, and from nothing else. Between two calls,
nothing happens on its own: the moment a turn runs out of time is `ClockView::$expiresAt`, and an application calls
`check()` then — a delayed Messenger message carrying the clock's identifier, or a scheduled task — so that the expiry is
told, and under `Overtime::Forfeit` the turn passes on. A check made early, or twice, changes nothing.
