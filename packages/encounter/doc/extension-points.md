# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the encounters | Implement `Contract\EncounterStore`, and pass it to `EncounterPlanner`. |
| Act on what happened | Implement `Contract\EncounterObserver`, and pass it to `EncounterPlanner`. |
| Declare the difficulty of a game system | Give the system difficulty numbers `encounter` in `tilttek/rpg-systems`, read by `Rulebooks::fromSystems()`; or read data of its own with `Difficulty\Declaration::read()`, fill `Difficulty\BudgetRules` or `Difficulty\LevelRules`, or implement `Contract\DifficultyRules`, and pass them in `Rulebooks`, where they take the place of a system's. |
| Run the encounter in an initiative tracker | Implement `Contract\InitiativeHandOff`, and pass it to `EncounterPlanner`. |
| Say who is calling | Pass the member to each change; the service checks that it is the game master. |

## The store

`Contract\EncounterStore` keeps each `Encounter` at a revision:

- `encounter()` gives an encounter, or null when none of the identifier is kept;
- `save()` keeps the encounter at the next revision and returns it as kept. An encounter at revision 0 is new: when
  the store has one of the identifier, `EncounterConflict` is raised. Any other is kept only when the store holds it at
  that revision, and `EncounterConflict` is raised otherwise, nothing kept;
- `remove()` removes the encounter when the store holds it at its revision, and raises `EncounterConflict` otherwise.

`Encounter` holds strings, a boolean, an integer and up to 100 `Participant`s, each of strings, an enum whose values are
its string cases, an integer, a boolean and an optional integer: a row and a JSON column, or a table of participants
in order.

`Store\InMemoryEncounterStore` keeps them in memory, as long as the object lasts: it is for tests.

## The observer and its events

`Contract\EncounterObserver::notify()` is told each fact once the encounter is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\EncounterPrepared` | A new encounter was prepared. | its game master |
| `Event\EncounterRetitled` | Its title changed or was taken away. | — |
| `Event\ParticipantAdded` | A participant was added. | the participant |
| `Event\ParticipantChanged` | A participant was changed. | the participant |
| `Event\ParticipantMoved` | A participant moved in the order. | the participant |
| `Event\ParticipantRemoved` | A participant was removed. | the participant |
| `Event\EncounterLaunched` | The encounter was launched and handed to the tracker. | how many combatants |
| `Event\EncounterForgotten` | The encounter was removed from the store. | — |

Every event implements `Event\EncounterEvent`: the encounter, an identifier. A change that changes nothing tells no
event. An application sends a message from them — through Messenger, with the identifiers alone — and reads the rest
from the store.

## The rules of difficulty

`Contract\DifficultyRules` gives the key of its game system with `system()`, and assesses a list of participants with
`assess()`, as [Difficulty](difficulty.md) describes. An implementation keeps no state between calls, refuses a rating
it does not know with `InvalidEncounter`, and an encounter it cannot assess with `EncounterRefused`. Its bands are 1 to
12, their thresholds in order: `Difficulty\Assessment` refuses them otherwise.

## The initiative tracker

`Contract\InitiativeHandOff::handOff()` starts the encounter in the tracker with the combatants of the roster, once the
launch is kept. It refuses an encounter it already runs, so that handing the same roster again is safe.
[Initiative](initiative.md) shows an adapter for `tilttek/initiative-cards`.
