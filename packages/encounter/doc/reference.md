# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Encounters

| Type | Role |
|---|---|
| `Encounter` | An encounter: its game master, game system, title, participants in order, whether it was launched, its revision. |
| `Participant` | One line of an encounter: a character, or copies of an adversary or an ally, with its rating, controller, visibility, bonus and record. |
| `Kind` | Character, adversary or ally. |
| `EncounterPlanner` | The service: prepare, retitle, add, change, move, remove, assess, roster, launch and forget. |
| `Rulebooks` | The rules of difficulty of each game system, found by its key or its `GameSystem`; read from the difficulty numbers of systems, with the application's own in their place. |

## Difficulty

| Type | Role |
|---|---|
| `Difficulty\Assessment` | The cost, the bands with their thresholds, and the band reached. |
| `Difficulty\Band` | One band of difficulty and its threshold. |
| `Difficulty\BudgetRules` | Rules by budget: amounts by character level, costs by adversary challenge, multipliers by number. |
| `Difficulty\LevelRules` | Rules by level: budgets for a standard party, adjustments by its size, costs by level difference. |
| `Difficulty\Multiplier` | The percentage the cost is multiplied by from a number of adversaries on. |
| `Difficulty\Declaration` | Reads rules of difficulty from the difficulty numbers of a system, or from plain data. |

## Initiative

| Type | Role |
|---|---|
| `Initiative\InitiativeRoster` | What a tracker receives: the encounter, its game master, its system and its combatants in order. |
| `Initiative\RosterEntry` | One combatant: its identifier, participant, kind, controller, visibility, bonus and record. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\EncounterStore` | Keeps each encounter at a revision. |
| `Contract\EncounterObserver` | Told of each fact once the encounter is kept. |
| `Contract\DifficultyRules` | The rules of difficulty of one game system. |
| `Contract\InitiativeHandOff` | Hands a launched encounter to an initiative tracker. |
| `Store\InMemoryEncounterStore` | The encounters in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\EncounterEvent` | A fact of an encounter, by identifiers only. |
| `Event\EncounterPrepared` | A new encounter was prepared. |
| `Event\EncounterRetitled` | Its title changed or was taken away. |
| `Event\ParticipantAdded` | A participant was added. |
| `Event\ParticipantChanged` | A participant was changed. |
| `Event\ParticipantMoved` | A participant moved in the order. |
| `Event\ParticipantRemoved` | A participant was removed. |
| `Event\EncounterLaunched` | The encounter was launched. |
| `Event\EncounterForgotten` | The encounter was removed from the store. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\EncounterException` | Every exception of the library. |
| `Exception\InvalidEncounter` | A value out of its shape or its bounds. |
| `Exception\EncounterRefused` | A call not allowed on the encounter in its state. |
| `Exception\EncounterNotFound` | No encounter of the identifier. |
| `Exception\EncounterConflict` | The encounter changed since it was read, or already exists. |
| `Exception\UnknownSystem` | No rules of difficulty for the game system. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier. |
| `Internal\Rating` | The shape of a rating. |
| `Internal\Text` | The shape of a line of text. |
