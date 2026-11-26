# Conditions and effects

## The catalogue

The library knows no game system. The site declares the conditions of its system as `ConditionDefinition`s, gathered in
a `ConditionCatalogue` built once — from its own configuration, or from the terms of a system it reads elsewhere. A
condition is known by its identifier alone: its names and descriptions stay with the site, in its translations.

```php
new ConditionDefinition(
    'frightened',                     // the identifier
    Stacking::Highest,                // what a new application does
    maxValue: 4,                      // a value of 1 to 4; null for a condition without a value
    modifiers: [Modifier::add('checks', -1, perValue: true, type: 'status')],
    implies: [],                      // the conditions it brings with it
    duration: null,                   // its own duration; until removed when null
    decay: new Decay(1, Boundary::End),
);
```

| Bound | Value |
|---|---|
| Conditions in a catalogue | 1,000 |
| Highest value of a condition | 100 |
| Modifiers of a condition | 32 |
| Conditions a condition brings | 16 |
| Instances of a condition that stacks separately | 2 to 16 |

The catalogue refuses an identifier twice, a condition that brings one it lacks or one with a value, and a condition
that brings itself back through others. A definition refuses rules that contradict one another: a sum, a decay or a
modifier by the value on a condition without a value; separate instances with a most of 1, or several instances for
any other stacking.

## Stacking

`Conditions::apply()` applies a condition to a character by its `Stacking`:

| Stacking | When the character has it already |
|---|---|
| `Replace` | The applications in place end (`EndReason::Replaced`), and the new one starts. |
| `Highest` | When the new value is as high or higher, the one in place takes it, with the new duration, source and moment; otherwise nothing changes. A condition without a value is refreshed. |
| `Sum` | The values add up to the highest value; the one in place takes the new duration and source, and keeps the moment it was first applied. |
| `Separate` | Each application is its own, up to the most instances; one more is refused. |
| `Ignore` | Nothing changes. |

A new application gets an instance identifier, `i1`, `i2` and so on for the character, never given twice. A character
has 64 applications at most. Before applying, the service ends the conditions already past their end in real time.

## Values and decay

A condition with a value is applied with a value of 1 to its highest; one without is applied without. `setValue()`
changes the value of an application, and 0 removes it.

A `Decay` lowers the value by its amount at the start or the end of the affected character's own turns; at zero, the
condition ends with `EndReason::Decayed`. Frightened 2 with a decay of 1 at the end of the turn is frightened 1 after
its next turn, and gone after the one after.

## Durations

| Duration | Runs out |
|---|---|
| `Duration::rounds($n, $boundary, $anchor)` | After `$n` boundaries — the start or the end — of the anchor's turns: the target's, the source's, or the rounds'. |
| `Duration::gameTime($seconds)` | When the table has advanced the fiction by so many seconds. |
| `Duration::realTime($milliseconds)` | At the moment it was applied plus the milliseconds, read from the clock. |
| `Duration::untilRemoved()` | Never on its own: a mutation, a lost limb. |

Rounds count the boundaries after the condition is applied: a condition of 1 round to the end of the target's turn,
applied during that turn, ends at its end; applied during another character's turn, it ends at the end of the target's
next one. A duration on the source's turns needs the source, and counts the turns of the character named as source.
Durations hold 1 to 10,000 rounds, up to a hundred years of game time, and one second to 366 days of real time.

## Turns, rounds and time

The library has no turn order of its own: the application tells it each boundary, from its initiative tracker or its
game engine, with the characters whose conditions count.

| Call | Counts |
|---|---|
| `startTurn($tick, $character, $participants)` | The start of a character's turn: durations anchored on the target or the source at the start, decays at the start. |
| `endTurn($tick, $character, $participants)` | The end of a character's turn. |
| `startRound($tick, $participants)` | The start of a round: durations anchored on the round at the start. |
| `endRound($tick, $participants)` | The end of a round. |
| `advanceTime($tick, $seconds, $participants)` | Seconds of the fiction: durations in game time. |

A boundary counts for up to 200 participants, each kept on its own. Each carries a tick, an identifier of the boundary
— `enc-4:r3:hero-1:end` — that a participant records when the boundary changes their conditions: told again right
after, as a retried message would be, it counts once. A participant whose conditions the boundary does not change is not
written. When a participant's conditions changed meanwhile, `ConditionConflict` is raised, the participants before are
kept, and the same call made again counts only for the rest.

The conditions in real time end on their own moment: `view()` gives the next one, `ConditionsView::$nextExpiry`, and the
application calls `expire()` then. Until it does, `view()`, `has()`, `modifiers()` and `effective()` already leave them
out, and the next write ends them.

## What conditions bring

A condition brings others while it lasts: dying brings unconscious, which brings blinded and prone. The conditions
brought have no value, instance or duration of their own; they end with the ones that bring them. `has()` sees them, the
view lists them after the applications with the conditions that bring them, and their modifiers count once, however
many conditions bring them. A condition applied in its own right is not listed again as brought.

## Modifiers

A `Modifier` changes one statistic of the character, named by an identifier the site chooses:

| Operation | Effect |
|---|---|
| `Operation::Multiply` | Multiplies by 0 to 1,000 percent: 50 halves. |
| `Operation::Add` | Adds the amount, below zero to take away. |
| `Operation::Upgrade` | Raises to the amount at least. |
| `Operation::Downgrade` | Lowers to the amount at most. |
| `Operation::Override` | Sets to the amount. |

For each statistic, the operations run in that order: every multiplication, one after the other in the order the
conditions were applied, each rounded by the system's `Rounding`; the additions summed; the highest floor; the lowest
cap; the last override. A modifier `perValue` is multiplied by the condition's value: frightened 3 takes 3. Additions of
the same `type` do not stack: of them, only the highest bonus and the lowest penalty count; additions without a type
always stack.

`effective()` takes the base statistics — up to 1,000, each within a billion either way — and gives each one's
`Effect\StatValue`: its base, its value, and the modifiers that made the difference, to show the player why. A
statistic stays within a million billion either way; a modifier of a statistic not in the base changes nothing. A
condition the catalogue no longer has keeps its place on the character and modifies nothing.

## The view

`view()` gives a `View\ConditionsView`: the applications in the order applied, each with its instance, value, source,
what is left of its duration and its end in real time; then the conditions they bring; the next end in real time; and
the version read. `toArray()` gives it as plain data, the moments in RFC 3339 with milliseconds, to push to the table.
