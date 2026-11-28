# Exceptions

Every exception of the library implements `Exception\FactionsException`: catch it to catch them all.

| Exception | Extends | Raised when |
|---|---|---|
| `Exception\InvalidRoster` | `InvalidArgumentException` | A value is out of its shape or its bounds; a key, a subject, a character or a pair comes twice; a standing is off the scale; a relation or a membership names what the roster or the faction lacks; a call names a faction or a relation the roster does not hold. |
| `Exception\RosterNotFound` | `RuntimeException` | No roster is kept for the campaign. |
| `Exception\RosterRefused` | `RuntimeException` | The member is not the roster's game master; a new roster is not at version 0 or its campaign has one; an added faction holds standings or members. |
| `Exception\RosterConflict` | `RuntimeException` | The roster changed since it was read, or a new one has the campaign of a kept one. Nothing is kept: read it anew and make the change again. |

Each message says what was refused in plain English, with the keys and identifiers involved; it never holds the text
of a summary, a note or a reason.
