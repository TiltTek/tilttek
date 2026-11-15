# Reference

| Type | Role |
|---|---|
| `Sets` | Defines versions, measures progress, records completions and dispatches their events until they are delivered, for a holder or for the holders waiting the longest |
| `SetDefinition` | A version of a set: members, tiers and rewards; evaluates holdings; read from and written to arrays and JSON |
| `Tier` | A step of a set: a number of members at a level or higher, with its rewards |
| `Reward` | An opaque reward a set or a tier declares: kind, reference and quantity |
| `Holdings` | What a holder owns: each member with its highest level |
| `Evaluation` | A set measured against holdings: owned, missing, percentage, tiers reached |
| `Progress` | An evaluation with the holder's completions, those kept and those the call recorded |
| `Completion` | A set or tier completed once by a holder, with the version and the moment in UTC |
| `Identifiers` | The shapes of codes, members and holders, which other packages reuse |
| `UtcClock` | The system's clock in UTC, the default clock |
| `DeliveryBackoff` | How long `Sets::deliverPending()` waits before telling again a holder whose delivery failed, and from how many failures in a row each one is reported |
| `Contract\DefinitionStore` | Where the versions of the sets are kept |
| `Contract\CompletionStore` | Where the completions are kept |
| `Contract\PendingDeliveries` | What a completion store adds for `Sets::deliverPending()`: the holders with undelivered completions and the failures of their delivery |
| `Store\InMemoryDefinitionStore`, `Store\InMemoryCompletionStore` | The stores in memory; the completion store also implements `PendingDeliveries` |
| `Event\SetCompleted` | Dispatched once, when a holder completes a set, with its rewards |
| `Event\TierReached` | Dispatched once, when a holder reaches a tier, with its rewards |
| `Event\DeliveryKeepsFailing` | Dispatched when the delivery of a holder's completions failed again, from the number of failures the backoff reports |
| `Exception\SetsException` | The interface of every exception of the library |
| `Exception\InvalidSet`, `UnknownSet`, `DefinitionConflict`, `DeliveryFailed`, `UnsupportedStore` | See [Exceptions](exceptions.md) |

`ArrayInput` is internal.
