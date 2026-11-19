# tilttek/escape-room

**Escape rooms on chained puzzles: a countdown, hints counted in the final time, a host console, bookable sessions and a
leaderboard by consent.**

A room is a scenario of puzzles from `tilttek/puzzle-builder`, chained or opened in any graph the scenario allows, a
time to escape, and what a hint and a skipped puzzle cost. A team starts the countdown; it guesses, asks for hints, and
escapes when it solves the finale. From the console, the host pushes a hint to the hint screen, types a message, clears
the screen, pauses and resumes the countdown, skips a puzzle for a team that is stuck, or ends the game. Every hint,
asked or pushed, adds its penalty to the final time, never to the countdown: the time played plus the penalties ranks a
team.

Sessions are booked through a port the application implements over its reservations, or through the adapter the
library ships over `phil/reservation`: a private session for one party, which takes the whole room, or seats of a
shared session, up to the room's largest team. The public leaderboard shows a team only once it agrees, and it may change its mind.

It is a plain PHP library and keeps nothing: each move takes the team's game and gives the game to store after it, and
every move of the console is an event, for a bundle that pushes them to the hint screen in real time. The countdown is
read from a PSR-20 clock, never kept running. Locks, sensors and props are outside the library; its events and API stay
open to them.

## Status

| Part | State |
|---|---|
| `Room`: a scenario, its duration, the penalties of a hint and of a skip, the size of a team; chained rooms | Implemented and tested |
| `EscapeRooms`: start, guesses, hints asked and pushed, skips, puzzles opened, pauses, the hint screen, expiry, abandon, consent, results | Implemented and tested |
| `Game`, `Timer`, `GameOutcome`: the state of a game, written and read back; the countdown and the final time | Implemented and tested |
| `Booking\SessionBooker`, `Booking\Session`, `Booking\SeatBooking`: private and shared sessions | Implemented and tested |
| `Contract\SessionReservations`, with `Store\InMemorySessionReservations` | Implemented and tested |
| `Reservation\BookableSessionReservations` over `phil/reservation`, its `Contract\SeatLedger` and `Store\InMemorySeatLedger` | Implemented and tested |
| `Leaderboard`, `RoomResult`, `Standing`: the public leaderboard, by consent only | Implemented and tested |
| Events of the game, the console and the bookings, dispatched through PSR-14 | Implemented and tested |
| Exceptions under one `EscapeRoomException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Usage](doc/usage.md) | Rooms, games, the countdown, hints and penalties, the host console, storing a game, results and the leaderboard. |
| [Bookings](doc/bookings.md) | Private and shared sessions, the port to the reservations, and the adapter over `phil/reservation`. |
| [Accessibility](doc/accessibility.md) | Showing the countdown, the hint screen and the leaderboard. |
| [Drives and safeguards](doc/drives-and-safeguards.md) | Where the library stands in Tilttek's grid of drives, and the safeguards it carries. |
| [Extension points](doc/extension-points.md) | The ports, the events of the console, and what a bundle adds. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role, and its exceptions. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Room;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Hint;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\PuzzleEngine;

$room = Room::chain('crypt', 'The crypt', [
    new Puzzle('candle', 'The candle', new AnswerKey(['wick']), [new Hint('Look at the top')]),
    new Puzzle('tomb', 'The tomb', new AnswerKey(['1347'])),
], duration: 3_600, hintPenalty: 120);
$rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(10, 60), $eventDispatcher), $eventDispatcher);
$host = ResourceReference::of('member', '3');

$game = $rooms->start($room, ResourceReference::of('team', 'owls'));
$game = $rooms->pushHint($room, $game, 'candle', $host)->game;   // on the hint screen, 2 minutes added
$game = $rooms->guess($room, $game, 'candle', 'Wick')->game;
$game = $rooms->guess($room, $game, 'tomb', '1347')->game;      // escaped
$rooms->timer($room, $game)->final();                            // the time played plus 120 seconds
$game->toArray();                                                // to store
```

## Requirements

PHP 8.5 or later with `mbstring`, `tilttek/puzzle-builder` 0.1, `phil/resource` 0.1, `psr/clock`,
`psr/event-dispatcher` and `symfony/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
