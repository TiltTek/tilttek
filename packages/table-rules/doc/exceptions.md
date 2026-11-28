# Exceptions

Every exception of the library implements `Exception\TableRulesException`: catch it to catch them all.

| Exception | Extends | When |
|---|---|---|
| `Exception\InvalidRules` | `InvalidArgumentException` | A value out of its shape or its bounds: an identifier, a title, a text or a note empty, too long, of too many lines, not valid UTF-8 or holding a control character, a version number, a first version that does not require acceptance, a version without rule nor variant, a rule or a variant given twice, too many rules, variants or characters, a variant the catalogue does not offer, a rulebook whose baseline or decisions do not match its versions, too many players. |
| `Exception\RulesRefused` | `DomainException` | A change not allowed on the rulebook in its state: a version identical to the one that holds, the most versions, an answer to a version that no longer holds, the most players. |
| `Exception\RulesNotFound` | `RuntimeException` | The table has no rules published yet, or no version of the number. |
| `Exception\RulesConflict` | `RuntimeException` | The rulebook changed since it was read: the change is not kept, and is made again on what is read anew. |
