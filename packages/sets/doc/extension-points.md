# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\DefinitionStore` | The versions of the sets, never rewritten; `Store\InMemoryDefinitionStore` keeps them in memory | [Defining sets](defining-sets.md#versions) |
| `Contract\CompletionStore` | The completions, one per set, tier and holder; `Store\InMemoryCompletionStore` keeps them in memory | [Progress and completions](progress-and-completions.md#concurrency) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of each completion, through `Event\SetCompleted` and `Event\TierReached`, in idempotent handlers | [Progress and completions](progress-and-completions.md#delivery) |
| `Psr\Clock\ClockInterface` | The time of the completions, kept in UTC | [Installation](installation.md#wiring-the-library) |

Every point is passed to a constructor; `Sets` keeps no state between two calls.

## Implementing a store

- `DefinitionStore::add()` returns false, writing nothing, when the store holds that version of that set; `latest()`
  gives the highest version.
- `CompletionStore::add()` returns false, writing nothing, when the store holds a completion of the same
  `Completion::key()`: the set, the tier or none, and the holder. `of()` and `ofHolder()` list in the order of adding.
- A completion is added undelivered. `CompletionStore::undelivered()` lists a holder's undelivered completions in every
  set, in the order of adding; `markDelivered()` marks one delivered, and does nothing for a completion the store does
  not hold. A database store keeps a column of its own for it, such as a nullable moment of delivery.
- `Completion::toArray()` gives the values of a row, with the moment in UTC to the microsecond.
