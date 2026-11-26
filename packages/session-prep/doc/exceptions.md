# Exceptions

Every exception of the library implements `Exception\SessionPrepException`: catch it to catch them all.

| Exception | Raised when |
|---|---|
| `Exception\InvalidPreparation` | A value is out of its shape or its bounds — an identifier, a key, a text, a list too long, a key twice in a list, a version below 0, a limit, a checked item in a template —, a scene features a character or a place its preparation does not hold, a part is to move before itself, or no part has the key a call names. |
| `Exception\PreparationRefused` | The service does not allow the change: a new preparation is not a draft at version 0 or has the identifier of a kept one, the member is not the game master, a change moves a preparation to another campaign, the preparation is closed, a required item is not checked when it is marked ready, or a preparation is carried into itself or into one of another campaign. |
| `Exception\PreparationNotFound` | No preparation has the identifier. |
| `Exception\PreparationConflict` | The preparation changed since it was read, or a new one has the identifier of a kept one: read it again and make the change anew. |

Nothing is kept when an exception is raised.
