# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\DefinitionStore` | The versions of the sets, never rewritten; `Store\InMemoryDefinitionStore` keeps them in memory | [Defining sets](defining-sets.md#versions) |
| `Contract\CompletionStore` | The completions, one per set, tier and holder; `Store\InMemoryCompletionStore` keeps them in memory | [Progress and completions](progress-and-completions.md#concurrency) |
| `Contract\PendingDeliveries` | Optional, beside the completion store: the holders with undelivered completions and the failures of their delivery, for `Sets::deliverPending()` | [Progress and completions](progress-and-completions.md#delivery) |
| `DeliveryBackoff` | How long a holder whose delivery failed waits, and from how many failures in a row each one is reported | [Progress and completions](progress-and-completions.md#delivery) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of each completion, through `Event\SetCompleted` and `Event\TierReached`, in idempotent handlers, and of repeated failures, through `Event\DeliveryKeepsFailing` | [Progress and completions](progress-and-completions.md#delivery) |
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
- A completion store that also implements `Contract\PendingDeliveries` lets `Sets::deliverPending()` find the
  holders waiting. `holdersWithUndelivered($limit, $now)` gives each holder with an undelivered completion and no
  attempt postponed after `$now` once, at most `$limit` of them, in the order their oldest undelivered completion was
  added, and none when `$limit` is below 1. `failedDeliveries()` gives a holder's failures in a row, 0 for none;
  `postpone()` keeps them with the moment before which the holder is not listed; `forgetFailures()` forgets both, and
  erasing a holder forgets them too. A database store keeps them in a table of its own, by holder. A database store answers it with one query, such as the holders of the undelivered rows
  grouped by holder and ordered by their smallest row number, with an index on the column of delivery. A store that
  does not implement it keeps working with everything else.
- `Completion::toArray()` gives the values of a row, with the moment in UTC to the microsecond.
