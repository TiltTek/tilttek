# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Tilttek\PuzzleBuilder\PuzzleEngine` | The puzzle engine, with its limiter of guesses and its own points | — |
| `Contract\SessionReservations` | The reservations of the sessions | `Store\InMemorySessionReservations`, `Reservation\BookableSessionReservations` |
| `Contract\SeatLedger` | Where `Reservation\BookableSessionReservations` keeps the bookings | `Store\InMemorySeatLedger` |
| `Reservation\Contract\RoomBookables` | The `Bookable` of each room, with its opening hours and rules | none: a `Bookable` with the capacity alone |
| `Psr\EventDispatcher\EventDispatcherInterface` | Where the events go | none: nothing is dispatched |
| `Psr\Clock\ClockInterface` | The countdown and when a session starts | `Symfony\Component\Clock\NativeClock` |

Every point is passed to a constructor; `EscapeRooms` and `Booking\SessionBooker` keep nothing between two calls. The
checks of answers and the limiters are those of `tilttek/puzzle-builder`.

## The events

Each event is dispatched once its move is made, and names identifiers — the room, the team, the session, the puzzle,
the host — never a guess nor an answer. `Event\ScreenMessageSent` is the one that carries a text: the message the host
typed for the hint screen.

| Event | Carries |
|---|---|
| `Event\GameStarted` | The room, the team, the session and the start |
| `Event\GamePaused`, `Event\GameResumed` | The room, the team, the session, the host and the time |
| `Event\HintRequested` | The room, the team, the session, the puzzle, the tier, the penalty and the time |
| `Event\HintPushed` | The same, and the host |
| `Event\PuzzleSkipped` | The room, the team, the session, the puzzle, the penalty, the host and the time |
| `Event\ScreenMessageSent` | The room, the team, the session, the message, the host and the time |
| `Event\ScreenCleared` | The room, the team, the session, the host and the time |
| `Event\GameFinished` | The room, the team, the session, the outcome, the final time and the time it ended |
| `Event\ConsentChanged` | The room, the team, the session, and whether the result is public |
| `Event\SeatsBooked` | The room, the session, the booking, the party, the seats, and whether it is private |

## What a bundle adds

The library ends at its events and its plain values. A bundle for Symfony adds the rest:

- the games, the results and the bookings stored with Doctrine;
- the hint screen in real time: each move of the console is told to the screen by a Mercure update that names no
  more than the move, and the screen reads the hint, the message or the timer from the server;
- a scheduled task that calls `expire()` when a countdown reaches 0;
- the console of the host, as a Stimulus controller;
- the leaderboard page, and the eraser that `phil/privacy-bundle` calls to erase a member.

`tilttek/escape-room-bundle` is that bundle.

## Props and sensors

The library drives no lock, light nor sensor. An application that does listens to the events — `Event\PuzzleSolved`
of the engine opens a door, `Event\GameFinished` lights the exit — and calls the API from its own devices: a lock that
opens is a `guess()`, or a `skip()` by the host.
