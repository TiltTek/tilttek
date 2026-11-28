# Tables and turns

## The tables

| Call | What it does |
|---|---|
| `open($game, $tableId, $seats, $options, $seed)` | Opens a table of a tavern game; its first seat plays first |
| `play($tableId, $move, $expected)` | Plays a move; refused with `ConcurrentMove` when `$expected` is not the number of moves |
| `timeout($tableId, $seat, $sequence)` | Plays the timeout of a seat's turn, when it still applies |
| `view($tableId, $seat)` | What a seat sees of the table — the audience, with no seat |
| `state($tableId)` | The whole state, secrets included: for the server only |
| `ending($tableId, $seat)` | How the game ended for a seat, or null while it goes on |

## The game's own turns

A tavern game ends a turn by its rules — a die that shows 1, a card played, the third roll —, so its phase leaves the
turns to the game: the engine's turn order is `Free`, and the game keeps `Turns` in its data — the seats in the order
they play and the seat playing — and refuses, in `allows()`, a move of any other seat. `TavernGame::toAct()` says
whose turn it is, null once the game is over; the views give it as `turn`.

## The turn timer

The library depends on no clock. An application that gives turns a delay implements `Contract\TurnTimer`:

| Method | When |
|---|---|
| `started($tableId, $seat, $sequence)` | A seat's turn starts, after `$sequence` moves of the table: on opening, and after each move that leaves the game going |
| `stopped($tableId)` | The game is over |

The timer chooses the delay — a setting of the table, of the application — and, when it runs out, calls
`TavernTables::timeout($tableId, $seat, $sequence)` with what it was told. The timeout is played only when the seat
still plays after the same number of moves: a seat that played in time, or a game over, makes it a no-op that returns
the state. It is the move `tavern.timeout` (`{seat}`), made by the table itself, and the game ends the turn by its
rules: Pig holds, Ship, Captain and Crew stops, Crazy Eights draws a card when none was drawn, then passes. A timeout
never takes away more than the turn.

`Store\NullTurnTimer`, the default, keeps no clock: a turn lasts until it is played.

## Endings

`Ending::of($outcome, $seat)`, which `TavernTables::ending()` gives, tells the end of a game in one of three tones.

| `Tone` | When | What the application does |
|---|---|---|
| `Victory` | The seat is the only winner | It plays a celebration — the celebration recipe of its interface kit — announced in a live region and cut by a preference for reduced motion |
| `Tie` | The seat shares the win with others | The same celebration: a shared victory is a victory |
| `GoodGame` | The seat did not win, or nobody did, or the view is the audience's | A kind word and an invitation to play again: never a mocking message, sound or animation |

`Tone::celebrates()` is true for a victory, alone or shared. `Ending::$reason` is the game's word for the ending and
`Ending::$winners` the seats that won.
