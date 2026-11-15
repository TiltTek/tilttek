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
| `Contract\DefinitionStore` | Where the versions of the sets are kept |
| `Contract\CompletionStore` | Where the completions are kept |
| `Contract\PendingDeliveries` | What a completion store adds for `Sets::deliverPending()`: the holders with undelivered completions |
| `Store\InMemoryDefinitionStore`, `Store\InMemoryCompletionStore` | The stores in memory; the completion store also implements `PendingDeliveries` |
| `Event\SetCompleted` | Dispatched once, when a holder completes a set, with its rewards |
| `Event\TierReached` | Dispatched once, when a holder reaches a tier, with its rewards |
| `Exception\SetsException` | The interface of every exception of the library |
| `Exception\InvalidSet`, `UnknownSet`, `DefinitionConflict`, `DeliveryFailed`, `UnsupportedStore` | See [Exceptions](exceptions.md) |

## Symfony integration

Optional, on suggested packages; see [Resuming deliveries with Symfony](installation.md#resuming-deliveries-with-symfony).

| Type | Role |
|---|---|
| `Symfony\DeliverCommand` | The `tilttek:sets:deliver` command: `Sets::deliverPending()`, or `Sets::deliver()` with `--holder` |
| `Symfony\DeliverPendingCompletions` | The message that asks for a pending delivery; it carries the limit only, 100 by default |
| `Symfony\DeliverPendingCompletionsHandler` | Its handler, which calls `Sets::deliverPending()` |
| `Symfony\DeliverySchedule` | The `tilttek_sets` schedule: the message at an interval, five minutes by default, with an optional lock |

`ArrayInput` is internal.
