# Exceptions

Every exception of the library implements `Exception\TurnClockException`: catch it to catch them all.

| Exception | Extends | When |
|---|---|---|
| `Exception\InvalidClock` | `InvalidArgumentException` | A value out of its shape or its bounds: an identifier, a budget, an increment, too few or too many players, a player twice, a budget for a player not on the clock, a vacation too long or overlapping another, more than 8 vacations, a change of time of zero or too large. |
| `Exception\ClockRefused` | `DomainException` | A change not allowed on the clock in its state: started twice, not started, stopped, a turn passed by a player not holding it, a player named who is out of time or the one passing, a player out of time starting, a vacation already over, an identifier taken. |
| `Exception\ClockNotFound` | `RuntimeException` | No clock has the identifier, or the clock has no such player. |
| `Exception\ClockConflict` | `RuntimeException` | The clock changed since it was read: the change is not kept, and is made again on what is read anew. |
