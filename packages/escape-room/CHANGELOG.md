# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/escape-room` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `Room`: a scenario of `tilttek/puzzle-builder`, a duration of 60 to 86 400 seconds, the penalties of a hint and of a
  skip up to an hour, teams of 1 to 100 players; `Room::chain()` for puzzles opened one after the other, the last the
  finale.
- `EscapeRooms`: the countdown started for a team named by a stable identifier; guesses, hints asked or pushed by the
  host, skips and puzzles opened, refused while paused, out of time or over; the escape on the finale; pauses and
  resumes; messages and clearing of the hint screen; expiry once the time is up, at the moment the countdown reached 0;
  abandon; consent to the public leaderboard; the result of a team that escaped.
- `Game`, `GameOutcome` and `Timer`: the state of a game kept by the application, written as plain values in UTC and
  read back checked against the room; the time played within the duration, pauses left out, the time left, and the
  final time with the penalties of hints and skips, never taken from the countdown.
- `RoomGuess`, `RoomHint`, `RoomResult`, `Standing` and `Leaderboard`: the public leaderboard of a room, by consent only,
  by final time, hints and escape, ties sharing a position, 1 to 1 000 places.
- `Booking\Session`, `Booking\SessionMode`, `Booking\SeatBooking`, `Booking\SessionBooker` and `Booking\BookingRefusal`:
  private sessions for one party and shared sessions by seats, the room's rules checked before the reservations hold
  the booking, every refusal with its reason, those of the reservations included — closed, too soon, too far ahead,
  off schedule; a party named by an email address refused.
- `Contract\SessionReservations`, whose `reserve()` gives the refusal of the reservations or null, with
  `Store\InMemorySessionReservations`.
- `Reservation\BookableSessionReservations`: the reservations on `phil/reservation` (suggested), a room a `Bookable`
  whose capacity is its largest team and a session an interval of it, a private booking the whole capacity, the
  violations of the `Bookable` told as refusals; `Reservation\Contract\RoomBookables` for the opening hours and rules of each
  room; `Contract\SeatLedger`, deciding and writing under one lock per session, with `Store\InMemorySeatLedger`.
- `EscapeRooms::ERASED_KIND`: a team, a party or a host of the kind `erased` given by the caller refused with
  `InvalidRoom::erased()`; only an erasure writes one.
- `Event\GameStarted`, `Event\GamePaused`, `Event\GameResumed`, `Event\HintRequested`, `Event\HintPushed`,
  `Event\PuzzleSkipped`, `Event\ScreenMessageSent`, `Event\ScreenCleared`, `Event\GameFinished`, `Event\ConsentChanged`
  and `Event\SeatsBooked`, dispatched through PSR-14.
- `Exception\EscapeRoomException`, with `InvalidRoom`, `GameRefused` and `BookingRefused`.
- A fuzz target for stored games, and benchmarks of a game of 50 puzzles and of the leaderboard of 10 000 results.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
