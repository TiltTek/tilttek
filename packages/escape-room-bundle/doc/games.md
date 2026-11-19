# Games

`Tilttek\EscapeRoomBundle\Application\GameDesk` plays the games by their identifier. Each move reads the game from the
store, plays it through `EscapeRooms`, writes it back with the moment its countdown reaches zero — none while it is
paused or over —, records the result of a team that escaped, keeps what the hint screen shows, and tells the screens
that the game moved. It keeps nothing between two calls.

## Starting a game

```php
$game = $desk->start('g-01J8', 'crypt', ResourceReference::of('team', 'owls'), session: 's-2026-11-14-19h');
```

The identifier is the application's: 1 to 64 characters among `A-Z`, `a-z`, `0-9`, `-` and `_`, from a letter or a
digit. A game already there, a room the catalog does not hold and a malformed identifier throw
`Application\Exception\DeskRefused`; a team named by an email address or of the kind `erased` throws `InvalidRoom`.

## The moves

| Method | What it does | The screens are told |
|---|---|---|
| `game($id)` | Reads the game, as a `StoredGame`: its identifier, room and `Game` | — |
| `timer($game)` | The countdown, the time played and the penalties | — |
| `puzzles($game)` | The puzzles as the host sees them: status, hints revealed and left | — |
| `screen($id)` | What the hint screen shows, or null | — |
| `guess($id, $puzzle, $answer)` | Checks a guess | `finish`, on the escape |
| `requestHint($id, $puzzle)` | A hint asked by the players | — |
| `pushHint($id, $puzzle, $host)` | A hint pushed by the host, shown on the hint screen | `hint` |
| `skip($id, $puzzle, $host)` | A puzzle skipped by the host | `skip`, or `finish` |
| `pause($id, $host)`, `resume($id, $host)` | Stops and runs the countdown | `pause`, `resume` |
| `message($id, $host, $text)` | A message of the host, shown on the hint screen | `message` |
| `clearScreen($id, $host)` | Clears the hint screen | `clear` |
| `abandon($id)` | Ends the game | `finish` |
| `expire($id)` | Ends the game when its time is up; true when it ended now | `finish` |
| `expireDue($limit)` | Ends the games whose countdown reached zero, the earliest first | `finish`, for each |
| `consent($id, $public)` | The team agrees, or no longer agrees, to show its result | `consent` |
| `leaderboard($room, $limit)` | The public leaderboard of a room | — |

The refusals of the library — a game over, paused or out of time, a puzzle locked, a message too long — pass through
unchanged, and nothing is written when a move is refused.

## Results and the leaderboard

When a move ends the game escaped, its `RoomResult` is recorded under the game's identifier. The leaderboard reads the
results the teams agreed to show, by final time, then by fewer hints, then by who escaped first, and ranks them with
`Leaderboard::rank()`: teams with the same final time and hints share a place. A team that withdraws its consent leaves
the leaderboard at once.

## Expiry

The countdown is never kept running: `expire()` ends a game at the moment its countdown reached zero, its pauses
counted, and `expireDue()` finds the games due by the moment written with each. A game ended already, or whose time is
no longer up, is left as it is, so calling either twice ends each game once. [Scheduling](scheduling.md) runs it.
