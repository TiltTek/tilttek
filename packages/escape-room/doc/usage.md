# Usage

## A room

A `Room` has:

| Field | Meaning | Default | Bounds |
|---|---|---|---|
| `id`, `title` | Its identifier and the title shown | — | 1 to 64 characters among `a-z`, `0-9`, inner `-` and `_`, from a letter; title of 1 to 200 characters |
| `scenario` | Its puzzles, a `Tilttek\PuzzleBuilder\Scenario` | — | — |
| `duration` | The time a team has, in seconds | 3 600 | 60 to 86 400 |
| `hintPenalty` | The seconds a hint without a penalty of its own adds to the final time | 0 | 0 to 3 600 |
| `skipPenalty` | The seconds a puzzle skipped by the host adds to the final time | 0 | 0 to 3 600 |
| `minPlayers`, `maxPlayers` | The smallest and the largest team; the largest is the seats of a session | 2, 6 | 1 to 100 |

`Room::chain()` makes the most common room: its puzzles open one after the other, the first from the start, each next
one once the one before is solved, and the last is the finale. A room of any other graph — two tracks played at once
that meet at the vault, a puzzle the host hands out — takes a `Scenario` built as `tilttek/puzzle-builder` describes.

```php
$room = Room::chain('crypt', 'The crypt', [$candle, $crest, $tomb], duration: 3_600, hintPenalty: 120, skipPenalty: 300);
```

## A game

`EscapeRooms::start()` starts the countdown for a team, named by a stable identifier — never an email address —, with
the session it booked when there is one. Every move then takes the room and the team's `Game` and gives the game after
it. A team or a host of the kind `erased` (`EscapeRooms::ERASED_KIND`) is refused with `InvalidRoom`: only an erasure
writes such a reference, in place of a member.

| Move | What it does |
|---|---|
| `guess($room, $game, $puzzle, $answer)` | Checks a guess; solving the finale ends the game, escaped |
| `requestHint($room, $game, $puzzle)` | Reveals the next hint, asked by the players |
| `pushHint($room, $game, $puzzle, $host)` | Reveals the next hint, pushed by the host to the hint screen |
| `skip($room, $game, $puzzle, $host)` | Marks a puzzle solved for a team that is stuck |
| `open($room, $game, $puzzle)` | Opens a puzzle the host hands out |
| `pause($room, $game, $host)`, `resume($room, $game, $host)` | Stops and runs the countdown again |
| `message($room, $game, $host, $text)`, `clearScreen($room, $game, $host)` | Drives the hint screen |
| `expire($room, $game)` | Ends a game whose time is up |
| `abandon($room, $game)` | Ends a game before its time |
| `consent($room, $game, $public)` | Records whether the team shows its result on the public leaderboard |

A guess, a hint, a skip or an opening is refused with `GameRefused` while the game is paused, once its time is up, or
once it is over; the rules of the puzzles — locked, solved, no hint left, the limit of guesses — still hold, with the
exceptions of `tilttek/puzzle-builder`. A game over takes no move but `consent()`.

## The countdown

The library keeps no clock running: `EscapeRooms::timer()` reads the clock each time and gives a `Timer`, in whole
seconds:

| Field | Meaning |
|---|---|
| `played` | The seconds played, pauses left out, within the duration |
| `remaining` | The seconds left on the countdown; `timeUp()` once it reaches 0 |
| `penalty` | The seconds the hints and the puzzles skipped add |
| `hints`, `skips` | The hints revealed, asked or pushed, and the puzzles skipped |
| `paused` | Whether the countdown is stopped |
| `final()` | The time that ranks the team: `played` plus `penalty` |

A game paused, or over, reads the countdown at its pause or its end. A game whose time is up is still running until
`expire()` ends it, out of time: a scheduled task calls it when the countdown should reach 0, or the application calls
it when a move is refused with `GameRefused::timeUp`. The end it records is the moment the countdown reached 0, pauses
counted, whenever `expire()` is called.

## Hints and penalties

A hint costs its own `penalty` when the scenario gives it one, the room's `hintPenalty` otherwise; a skipped puzzle
costs the room's `skipPenalty`. Hints asked and pushed cost the same. The penalties are added to the final time, never
taken from the countdown: a team is never closer to losing for having asked. `RoomHint::$penalty` says what the hint
revealed cost, so that the hint screen shows it.

## The host console and the hint screen

The console's moves are events, which a bundle pushes to the hint screen in the room:

| Event | The hint screen |
|---|---|
| `Event\GameStarted` | Starts the countdown |
| `Event\HintRequested`, `Event\HintPushed` | Shows the hint — read from the scenario by its puzzle and tier — and its penalty |
| `Event\ScreenMessageSent` | Shows the host's message, 280 characters at most |
| `Event\ScreenCleared` | Back to the countdown alone |
| `Event\GamePaused`, `Event\GameResumed` | Stops and runs the countdown, saying so |
| `Event\PuzzleSkipped` | Says the puzzle was skipped, and its penalty |
| `Event\GameFinished` | The end: escaped, out of time or abandoned, with the final time |
| `Event\ConsentChanged` | Nothing: the leaderboard reads the consent |

The events of `tilttek/puzzle-builder` — a puzzle solved, opened, a wrong or partial guess — are dispatched by the
engine too, named by the scenario, whose identifier is the room's in a chained room.

## Storing a game

`Game::toArray()` gives plain values — the room, the team, the session, the start, the pause, the paused seconds, the
end and its outcome, the progress of the puzzles and the consent —, every time in UTC to the microsecond;
`Game::fromArray()` reads them back, checked against the room, and refuses with `InvalidRoom` what does not read back.
Two moves at once on the same game read the same values: the application stores the game with a version and refuses
the second write.

## Results and the leaderboard

`EscapeRooms::result()` gives the `RoomResult` of a team that escaped: its final time, the time played, the penalties,
the hints, the skips, when it escaped and whether it agreed to show it. `Leaderboard::rank()` ranks the results of one
room:

- only those whose team agreed — a team that did not is never shown, and the leaderboard is private by default;
- by final time, then by fewer hints, then by who escaped first;
- teams with the same final time and the same hints share a position;
- 10 places by default, 1 000 at most.

```php
$standings = Leaderboard::rank('crypt', $results, 20);
$standings[0]->position;              // 1
$standings[0]->result->finalSeconds;  // shown with the hints beside it
```

Erasing a team, at its request, erases its games and results from the application's storage; the library keeps none.

## Celebrating

An escape is the moment for the celebration component of PHIL UX, on `Event\GameFinished` with `GameOutcome::Escaped`;
it respects `prefers-reduced-motion`.
