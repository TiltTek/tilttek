# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## The catalogue

| Type | Role |
|---|---|
| `ConditionCatalogue` | The conditions of a game system, and the ones each brings. |
| `ConditionDefinition` | A condition: stacking, value, decay, modifiers, conditions brought and duration. |
| `Stacking` | What a new application does to one in place. |
| `Decay` | How a value falls at the affected character's turns. |
| `Duration` | How long a condition lasts, and what is left of it. |
| `DurationKind` | Rounds, game time, real time, or until removed. |
| `Boundary` | The start or the end of a turn or a round. |
| `Anchor` | Whose turns a duration counts: the target's, the source's, or the rounds. |
| `Modifier` | What a condition does to one statistic. |
| `Operation` | Multiply, add, upgrade, downgrade or override. |

## The conditions of a character

| Type | Role |
|---|---|
| `Conditions` | The service: apply, remove, set a value, turns, rounds, game time, expiry, view and effects. |
| `CharacterConditions` | A character's applications, the last tick counted and the version. |
| `ActiveCondition` | One application: instance, condition, value, source, duration left and end. |
| `EndReason` | Removed, expired, decayed or replaced. |
| `View\ConditionsView` | A character's conditions to show the table, and the next end in real time. |
| `View\ConditionState` | One condition in the view, applied or brought. |

## Effects

| Type | Role |
|---|---|
| `Effect\EffectCalculator` | The statistics once the modifiers are counted. |
| `Effect\AppliedModifier` | A modifier in force, its condition and its amount. |
| `Effect\StatValue` | A statistic's base, value and the modifiers that made the difference. |
| `Rounding` | How a multiplication rounds. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\ConditionStore` | Keeps each character's conditions at a version. |
| `Contract\ConditionObserver` | Told of the events of the conditions. |
| `Store\InMemoryConditionStore` | The conditions in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\ConditionEvent` | Every event: the character, the instance and the condition. |
| `Event\ConditionApplied` | A condition was applied. |
| `Event\ConditionChanged` | An application changed. |
| `Event\ConditionEnded` | An application ended, and why. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\ConditionsException` | Every exception of the library. |
| `Exception\InvalidCondition` | A value out of its shape or its bounds. |
| `Exception\ConditionRefused` | A change the character's conditions do not allow. |
| `Exception\ConditionNotFound` | No condition or instance has the identifier. |
| `Exception\ConditionConflict` | The conditions changed since they were read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier. |
| `Internal\Millis` | Instants as milliseconds since the epoch. |
