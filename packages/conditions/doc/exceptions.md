# Exceptions

Every exception of the library implements `Exception\ConditionsException`: catch it to catch them all.

| Exception | Extends | When |
|---|---|---|
| `Exception\InvalidCondition` | `InvalidArgumentException` | A value out of its shape or its bounds: an identifier, a value missing, given or too high, a duration, a decay, a modifier, a definition whose rules contradict one another, a catalogue with an identifier twice, a missing or valued condition brought, or a loop, too many conditions, statistics or participants, a time advance out of its bounds, a duration on the source's turns without a source. |
| `Exception\ConditionRefused` | `DomainException` | A change not allowed on the character in its state: the most conditions, the most instances of a condition, a value set on a condition without one or that the catalogue no longer has. |
| `Exception\ConditionNotFound` | `RuntimeException` | The catalogue has no condition of the identifier, or the character has no such instance. |
| `Exception\ConditionConflict` | `RuntimeException` | The character's conditions changed since they were read: the change is not kept, and is made again on what is read anew. |
