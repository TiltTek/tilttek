# Exceptions

Every exception of the library implements `Exception\InitiativeException`: catch it to catch them all.

| Exception | Extends | When |
|---|---|---|
| `Exception\InvalidEncounter` | `InvalidArgumentException` | A value out of its shape or its bounds: an identifier, the draws of a combatant, too many combatants, a combatant twice, a combatant joining with a card, a face the ranking does not know, an order of suits. |
| `Exception\InitiativeRefused` | `DomainException` | A change not allowed: by someone other than the game master or the controller, on an ended encounter, the turn of a combatant not holding it, a next combatant not waiting, an identifier taken, a deck too small. |
| `Exception\EncounterNotFound` | `RuntimeException` | No encounter has the identifier, or the encounter has no such combatant. |
| `Exception\EncounterConflict` | `RuntimeException` | The encounter changed since it was read: the change is not kept, and is made again on what is read anew. |
