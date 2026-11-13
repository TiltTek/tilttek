# Phases and the state machine

A game's phases and the transitions between them make a state machine of Symfony Workflow. `GameDefinition` builds it
in `Phases`, exposed as `$definition->machine`:

```php
new GameDefinition('scene', [
    new Phase('exploration'),
    new Phase('encounter', TurnOrder::Initiative),
    new Phase('aftermath'),
], [
    new Transition('start-encounter', ['exploration', 'aftermath'], 'encounter'),
    new Transition('end-encounter', ['encounter'], 'aftermath'),
    new Transition('explore', ['encounter', 'aftermath'], 'exploration'),
]);
```

A `Transition` has a name, the phases it may leave from and the phase it leads to. Workflow's `StateMachineValidator`
checks the definition — a state machine has one phase at a time, and a transition leaving one phase has a name of its
own —, and a definition it refuses is refused with `InvalidGame`.

| Method | What it gives |
|---|---|
| `can()` | Whether a transition may leave a phase |
| `apply()` | The phase a transition leads to, or `IllegalMove` |
| `from()` | The transitions that may leave a phase |
| `mermaid()` | The machine as a Mermaid flowchart, the current phase marked when one is given |

A seat changes the phase with the engine's move `engine.transition`, `Move::transition('start-encounter', $seat)`;
the game's `allows()` decides who may, and its `enter()` gives the data of the phase entered. The turn starts again
in the new phase, in its turn order, from round 1.

## Why Symfony Workflow fits replay

Replaying a log applies its moves again, so a change of phase must depend on nothing but the phase it leaves and the
transition it names. Workflow's state machine does exactly that when it is given no event dispatcher: no guard, no
listener, no clock is consulted, and applying the same transitions from the same initial phase always reaches the
same phase. The engine therefore builds the machine without a dispatcher, and asks the game's `allows()` — a pure
function of the state — for what Workflow guards would otherwise decide.

What Workflow brings is kept: its definition and validator, the transitions enabled from a phase, its exceptions for a
transition that does not apply, and its dumpers, which draw a game's machine for its documentation. The marking lives
in a cursor made for each move and dropped after it: the phase itself is kept in `GameState`, a value the log
rebuilds.
