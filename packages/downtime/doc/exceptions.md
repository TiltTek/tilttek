# Exceptions

Every exception of the library implements `Exception\DowntimeException`: catch it to catch them all.

| Exception | Raised when |
|---|---|
| `Exception\InvalidDowntime` | A value is out of its shape or its bounds — an identifier, a key, a text, a dice expression, an amount, a change, a band, a list too long or empty, a key or a resource twice, a version below 0, a limit, the days of an advance —, an activity's resolution names an outcome it does not declare, the bands of a roll overlap or leave a gap, the values of an entry do not agree with its stage, or the game master names an outcome the activity lacks. |
| `Exception\UnknownActivity` | The rules of the system declare no activity of the key. It carries `system` and `activity`. |
| `Exception\DowntimeRefused` | The service does not allow the change: the member neither plays the character nor runs the campaign, or does not run it for a call of the game master; an entry has the identifier; the queue of the character is full; the campaign has more queued entries than one advance reads; or the entry is not at a stage the change starts from. |
| `Exception\EntryNotFound` | No entry has the identifier. |
| `Exception\DowntimeConflict` | An entry changed since it was read, or a new one has the identifier of a kept one: read it again and make the change anew. |

Nothing is kept and nothing is told when an exception is raised.
