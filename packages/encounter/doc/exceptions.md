# Exceptions

Every exception of the library implements `Exception\EncounterException`: catch it to catch them all.

| Exception | Extends | When |
|---|---|---|
| `Exception\InvalidEncounter` | `InvalidArgumentException` | A value out of its shape or its bounds: an identifier, a rating, a title, a quantity, a bonus, too many participants or combatants, a combatant twice, both sides of a placement, rules of difficulty out of their bounds or their order, a rating the rules do not know, data the declaration reader refuses. |
| `Exception\EncounterRefused` | `DomainException` | A call not allowed on the encounter in its state: by someone other than its game master, on a launched encounter, on a participant it does not hold, a move beside itself, a launch without participant, an encounter the rules cannot assess. |
| `Exception\EncounterNotFound` | `RuntimeException` | No encounter of the identifier is kept. |
| `Exception\EncounterConflict` | `RuntimeException` | The encounter changed since it was read, or one of the identifier already exists: nothing is kept, and the change is made again on what is read anew. |
| `Exception\UnknownSystem` | `RuntimeException` | No rules of difficulty are declared for the game system, or no rulebooks are given to assess. |
