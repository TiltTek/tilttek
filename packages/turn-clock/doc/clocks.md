# Clocks and time controls

Every duration is in milliseconds.

## A time control

`TimeControl` holds:

- **the budget** of each player, 1 second to 31 days;
- **the mode**, a `Mode`, and **the increment**, 0 to 1 day:

  | Mode | What the increment does |
  |---|---|
  | `Mode::Fischer` | It is added to the budget after each turn. |
  | `Mode::Bronstein` | The time spent in a turn is given back after it, up to the increment. |
  | `Mode::Delay` | The budget runs only once the increment has elapsed in each turn. |
  | `Mode::PerTurn` | Unused: each turn has the whole budget again, and nothing carries over. |

- **the overtime**, an `Overtime`: `Overtime::Continue` counts the time over below zero, and the table decides;
  `Overtime::Forfeit` puts the player out of time, their budget at zero, their turns skipped.

`TimeControl::daysPerTurn(3)` is the control of a game by correspondence: three days per move, each turn with the three
days again, a player out of them out of the game.

## A clock

`TurnClock::create()` makes a clock for 1 to 20 players, in seat order, each with the control's budget or the budget given
for them — a handicap, a new player given more time. It is not started. Identifiers are 1 to 128 letters, digits, `_`,
`.`, `:` or `-`, starting with a letter, never a name or an address.

`start()` runs the first player's turn: the first in seat order, or the one named, who must not be out of time.

## Turns

`pass()` ends the turn of the player holding it: the call names them, so that two presses never pass two turns. The time
counted in the turn is charged — under `Mode::Delay`, only what is past the delay — and the increment given, as the mode
says. Then the next turn starts: the player named, who is another player and not out of time, or else the next one in
seat order, the seats wrapping, skipping the players out of time. When every other player is out, the same player plays
on; when every player is out, the clock stops. Under `Mode::PerTurn`, the next player's budget is whole again.

A `PlayerClock` keeps what was left of a player's budget when their turn started, their turns ended and the time they
spent. `TableClock::remaining()` gives what is left at a moment, the turn in progress counted.

## Running out of time

The time a turn runs out is the moment in `ClockView::$expiresAt`. An application schedules `check()` at that moment — or
calls it on each display — and `check()` acts when the budget is spent:

- under `Overtime::Continue`, `TimeExpired` is told, once per turn, and the turn goes on, the budget going below zero;
- under `Overtime::Forfeit`, `TimeExpired` is told and the turn passes on, as `pass()` does, the player out of time.

A turn ended after its budget ran out tells `TimeExpired` with it, unless `check()` told it already. Under
`Overtime::Continue`, a player who starts a turn below zero is not told again: their increment may bring them back above.

## Pauses

`pause()` stops the clock for the whole table: the time counted so far in the turn is kept, and no time counts until
`resume()`. A turn passed while paused starts paused. Pausing a paused clock, or resuming a running one, changes nothing.

## Vacations

`declareVacation()` records a `Vacation` of a player, at most 60 days long: while it lasts, their clock does not run, in
their turn or in a turn to come. A player keeps at most 8 vacations, none overlapping another; those already over are
forgotten, and one declared once over is refused. A vacation already begun counts from the moment it is declared: the time
the turn in progress counted before stays counted. `ClockView::$expiresAt` skips the current player's vacations to come.

## Adjustments

`adjust()` adds time to a player's budget, or takes it away with a negative delta, at most 31 days either way. Under
`Overtime::Forfeit`, a budget goes no lower than zero, and a player out of time given time back plays again. A current
player brought back above zero by it may be told `TimeExpired` again.

## Stopping

`stop()` charges the time of the turn in progress, without ending the turn or giving the increment, and stops the clock:
it changes no more. The clock remembers who held the turn.

## The view

`view()` gives a `ClockView`, whose `toArray()` is sent to the table:

- the clock, the player holding the turn, whether it is paused or stopped, the version read;
- `expiresAt`, when the turn in progress runs out of time — now once it has — or null when the clock does not run;
- a `PlayerTime` per player, in seat order: what is left, below zero in overtime, the overtime, the turns ended, the time
  spent, and whether their clock runs, they are out of time, or on vacation.
