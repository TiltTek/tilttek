# Reference

| Type | Role |
|---|---|
| `Room` | A room: its scenario, duration, penalties and team sizes; chained rooms |
| `EscapeRooms` | The moves of a game, the host console, the timer, consent and results |
| `Game` | The state of a team's game, written and read back as plain values |
| `GameOutcome` | `Escaped`, `TimeUp` or `Abandoned` |
| `Timer` | The countdown, the time played, the penalties and the final time |
| `RoomGuess` | What a guess or a skip gave, and the game after it |
| `RoomHint` | The hint revealed, its penalty, and the game after it |
| `RoomResult` | The result of a team that escaped |
| `Leaderboard` | The public leaderboard of a room, by consent |
| `Standing` | A place on the leaderboard |
| `Booking\Session` | A session of a room: its times and how its seats are sold |
| `Booking\SessionMode` | `Private`, `Shared` or `Either` |
| `Booking\SeatBooking` | A booking of a session by a party |
| `Booking\SessionBooker` | Books sessions under the room's rules, and gives the seats left |
| `Booking\BookingRefusal` | Why a booking is refused |
| `Contract\SessionReservations` | The application's reservations of the sessions |
| `Store\InMemorySessionReservations` | The reservations in memory |
| `Reservation\BookableSessionReservations` | The reservations on `phil/reservation`, kept by a `Contract\SeatLedger` |
| `Reservation\Contract\RoomBookables` | The `Bookable` of `phil/reservation` that stands for a room, with its rules |
| `Contract\SeatLedger` | Where the adapter keeps the bookings, deciding and writing under one lock per session |
| `Store\InMemorySeatLedger` | The bookings of the adapter in memory |
| `Event\GameStarted` | A game started |
| `Event\GamePaused` | A game paused by the host |
| `Event\GameResumed` | A game resumed by the host |
| `Event\HintRequested` | A hint asked by the players |
| `Event\HintPushed` | A hint pushed by the host to the hint screen |
| `Event\PuzzleSkipped` | A puzzle skipped by the host |
| `Event\ScreenMessageSent` | A message of the host for the hint screen |
| `Event\ScreenCleared` | The hint screen cleared |
| `Event\GameFinished` | A game over, with its outcome and final time |
| `Event\ConsentChanged` | A team that agrees, or no longer agrees, to show its result |
| `Event\SeatsBooked` | A session booked |

## Exceptions

| Exception | Thrown when |
|---|---|
| `Exception\EscapeRoomException` | The interface of every exception of the library |
| `Exception\InvalidRoom` | A room, a session, a booking, a message, a leaderboard limit or a stored game the library refuses; a team or a party named by an email address; a team, a party or a host of the kind `erased`; a `Bookable` that does not hold the capacity of its room |
| `Exception\GameRefused` | A move the game does not allow: over, paused, out of time, time not up, another room, no result |
| `Exception\BookingRefused` | A booking refused, with its `Booking\BookingRefusal` |

The exceptions of `tilttek/puzzle-builder` — a puzzle locked or solved, a guess malformed — pass through unchanged.
Nothing changes when an exception is thrown.
