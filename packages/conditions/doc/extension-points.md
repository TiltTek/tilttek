# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the conditions | Implement `Contract\ConditionStore`, and pass it to `Conditions`. |
| Act on what happened | Implement `Contract\ConditionObserver`, and pass it to `Conditions`. |
| Declare the system's conditions | Build a `ConditionCatalogue` of `ConditionDefinition`s, and pass it to `Conditions`. |
| Round as the system does | Pass an `Effect\EffectCalculator` with its `Rounding` to `Conditions`. |
| Give the time | Pass a PSR-20 `ClockInterface`. |
| End the conditions in real time | Schedule `Conditions::expire()` at `ConditionsView::$nextExpiry`. |
| Count the turns | Call `startTurn()`, `endTurn()`, `startRound()` and `endRound()` from the turn order. |
| Say who may apply or remove a condition | Check it in the application before calling `Conditions`. |

## The store

`Contract\ConditionStore` keeps each character's conditions, at a version:

- `conditions()` gives a character's, or null when none were ever kept;
- `save()` keeps them at the next version and returns them. Conditions at version 0 are new, and the store has none for
  the character; any others are kept only when the store holds them at that version, and `ConditionConflict` is raised
  otherwise, nothing kept.

Two game masters applying a condition at once both read version 7: the first save keeps version 8, and the second is
refused with `ConditionConflict`; the application reads anew and applies again.

`Store\InMemoryConditionStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps
them in its database. `CharacterConditions` holds strings and integers — the character, the number of the last instance
given, the last tick, the version — and up to 64 `ActiveCondition`s, each strings, an integer value, a moment, an
optional moment of its end, and a `Duration` of an integer and three enumerations whose values are their string cases.

## The observer and its events

`Contract\ConditionObserver::notify()` is told each fact once the conditions are kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\ConditionApplied` | A condition was applied as a new instance. | |
| `Event\ConditionChanged` | An instance changed: its value, or a new application refreshed it. | |
| `Event\ConditionEnded` | An instance ended. | the `EndReason`: removed, expired, decayed or replaced |

Every event implements `Event\ConditionEvent`: the character, the instance and the condition, all identifiers. Within
one call, the conditions that ran out in real time come first, then a replaced instance, then the new one. An
application sends a message from them — through Messenger, with the identifiers alone — and reads the rest from the
store: a push of the view to the table, a notice to the player whose character is no longer stunned. A duration in
rounds running down is not an event; the view shows what is left.

## The catalogue and the system data

The catalogue is plain data the application builds once, from its configuration or from the terms of its game system,
and shares between requests. The behaviours are a closed list — stacking, values, decay, durations, conditions brought,
five operations — and the library runs no code of the site's; a rule outside the list stays in the application, which
reads the view and the events.

## The clock and the calls

`Conditions` reads the time from the PSR-20 `ClockInterface` it is given, and from nothing else. Between two calls,
nothing happens on its own: the moment the next condition in real time ends is `ConditionsView::$nextExpiry`, and an
application calls `expire()` then — a delayed Messenger message carrying the character's identifier, or a scheduled
task. An expiry checked early, or twice, changes nothing. The turns and the time of the fiction are told by the
application, each boundary with its tick, so that a retried message counts once.
