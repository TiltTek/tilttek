# Tables, moves and turns

## Opening a table

```php
$engine = new Engine();
$table = $engine->start($game, 'table:42', ['seat:ann', 'seat:bob'], ['target' => 30]);

$table->log;     // the truth: game, version, seed, seats, options, moves
$table->state;   // the state after the setup
```

The seats are identifiers the application gives, in the order of the table: an ASCII letter, then letters, digits,
`:`, `.`, `_` or `-`, 128 characters at most — `seat:3`, `member:44` —, never a number alone, so that a seat stays a
text as the key of a JSON mapping. Their number must lie within the game's bounds, and none is given twice. The seed is
drawn from the system's secure source unless the caller gives one.

## Moves

A `Move` has a type, the seat that makes it, and a payload of plain values — texts, numbers, booleans, null and arrays
of them, 8 levels deep and 16 384 bytes of JSON at most. A move without a seat is the application acting for the
table — a timer that ends a turn —: it is not held to the turns, but the game's `allows()` still decides.

```php
$table = $engine->play($game, $table, new Move('race.step', 'seat:ann', ['steps' => 2]));
```

The engine refuses with `IllegalMove` a move at a table whose game is over, from a seat not at the table, out of the
seat's turn, or that the game refuses. The engine's own moves are:

| Move | Payload | What it does |
|---|---|---|
| `Move::endTurn($seat)` | — | Ends the seat's turn; in a simultaneous round, the table itself ends the round |
| `Move::transition($name, $seat)` | `{transition}` | Changes the phase ([Phases and the state machine](phases.md)) |
| `Move::initiative($scores, $seat)` | `{scores: {seat: int}}` | Sets the initiative of seats |
| `Move::delay($seat, $after)` | `{after}` | Moves the acting seat after a seat that acts later |

`engine.end-turn` and `engine.delay` are held to the turns; `engine.transition` and `engine.initiative` are not, and
the game decides who may make them.

## Turns

`$state->turn` is a `Turn`: its kind, the round from 1, the order of the seats, the seat acting, the seats still to act
in a simultaneous round, and the moves made in the turn. `toAct()` lists the seats that may act now, and `allows()`
says whether one may.

- In a free phase, every seat may act and there is no turn to end.
- In a sequential or initiative phase, the turn passes to the next seat in the order, and after the last to the first
  of a new round.
- In a simultaneous phase, each seat ends its own turn once; the round ends when none is left.
- A phase with `movesPerTurn` ends a seat's turn by itself after that many moves of the game.
- A move type the game declares out of turn may be made by any seat at any time, and does not count in the turn.

## Initiative and delays

`engine.initiative` merges its scores into `$state->initiative`, kept from one phase to the next. A phase by
initiative orders the seats from the highest score; equal scores keep the order of the table, and seats without a
score come last, in the order of the table. Setting the initiative during such a phase orders the round again from
its first seat, the round number kept.

A seat delays its turn with `engine.delay` after a seat that acts later in the round: it moves there, and the seat
that followed it acts.
