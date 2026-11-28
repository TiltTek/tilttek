# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Encounters

| Type | Role |
|---|---|
| `InitiativeTracker` | The service: start, deal, end a turn, join, leave, defeat, revive, hide, reveal, end and view. |
| `Encounter` | An encounter: its game master, deck, piles, combatants in turn order, round and version. |
| `Combatant` | One combatant: its controller, whether it is hidden or defeated, its draws, its card and whether it acted. |
| `StandardRanking` | The ranking of the 52-card deck and its jokers, with an order of the suits. |
| `View\InitiativeView` | What one viewer may see of an encounter. |
| `View\Slot` | One combatant as a viewer sees it. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\EncounterStore` | Keeps the encounters at a version. |
| `Contract\CardRanking` | Orders the faces of a deck, and says which reshuffle. |
| `Contract\InitiativeObserver` | Told of the events of an encounter. |
| `Store\InMemoryEncounterStore` | The encounters in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\InitiativeEvent` | Every event: the encounter. |
| `Event\CombatantEvent` | The events of one combatant: the encounter and the combatant. |
| `Event\EncounterStarted` | An encounter was started. |
| `Event\RoundDealt` | A round was dealt. |
| `Event\DeckReshuffled` | The discard pile was shuffled back. |
| `Event\TurnStarted` | A combatant's turn started. |
| `Event\TurnEnded` | A combatant's turn ended. |
| `Event\RoundEnded` | Every combatant standing has acted. |
| `Event\CombatantJoined` | A combatant joined. |
| `Event\CombatantLeft` | A combatant left. |
| `Event\CombatantDefeated` | A combatant was defeated. |
| `Event\CombatantRevived` | A defeated combatant stands again. |
| `Event\CombatantRevealed` | A hidden combatant was revealed. |
| `Event\CombatantHidden` | A combatant was hidden. |
| `Event\EncounterEnded` | An encounter was ended. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\InitiativeException` | Every exception of the library. |
| `Exception\InvalidEncounter` | A value out of its shape or its bounds. |
| `Exception\InitiativeRefused` | A change the tracker does not allow. |
| `Exception\EncounterNotFound` | No encounter or combatant has the identifier. |
| `Exception\EncounterConflict` | The encounter changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier. |
