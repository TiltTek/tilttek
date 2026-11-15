# Progress and completions

## Holdings

`Holdings` is what a holder owns, as the application reads it from its own store: `Holdings::of($identifiers)` owns
each one at level 1, `Holdings::withLevels(['d4' => 2, 'd6' => 1])` at the level given, 0 meaning not owned. Members
that are not in the set are ignored. Holdings hold at most 100 000 members, each at a level of at most 1 000.

## The evaluation

`SetDefinition::evaluate($holdings)` measures a set against holdings without recording anything:

| Of `Evaluation` | Gives |
|---|---|
| `$owned`, `$missing` | The members owned and missing, in the set's order |
| `total()`, `ownedCount()` | *12 of 20* |
| `percent()` | The share owned, rounded down: 100 only when the set is complete |
| `isComplete()` | Every member owned, at any level |
| `$tiersReached`, `hasReached($code)`, `nextTier()` | The tiers reached, in order, and the first tier not reached |

## Recording

`Sets::progress($set, $holder, $holdings)` gives a `Progress` and records nothing. `Sets::record()` gives the same,
after recording each completion reached for the first time — the set's own, then its tiers in their order — and
dispatching, for each completion of the holder in the set not yet delivered:

- `Event\SetCompleted`, with the `Completion` and the rewards of the set;
- `Event\TierReached`, with the `Completion` of the tier and its rewards.

A `Completion` holds the set, the holder, the tier or null for the set itself, the version of the set it was earned
under, and its moment in UTC. The store keeps one per set, tier and holder, so a completion is told once: a holder who
trades a member away and gets it back is told nothing more. The application calls `record()` when a holder's holdings
change — a badge earned, an item received —, and grants the rewards when it hears the events.

## Delivery

The store keeps, beside each completion, whether its event was **delivered**. A completion is added undelivered, and
marked delivered once its event was dispatched without an exception:

- When a handler throws, its completion stays undelivered; the other completions are told all the same, and the first
  exception is thrown again once they are.
- The next `record()` for that holder and set tells every completion of the set still undelivered, with the rewards of
  the version it was earned under, even when it records nothing new.
- `Sets::deliver($holder)` tells every undelivered completion of the holder, in every set, and returns how many were
  delivered: the application calls it to resume without waiting for the holdings to change, from a retry or the
  holder's next visit.
- `Sets::deliverPending($limit)` does the same for the holders that have undelivered completions, at most `$limit` of
  1 to 10 000, the holder waiting the longest first, and returns how many completions were delivered: a command or a
  scheduled task calls it without knowing which holders are waiting. The completion store also implements
  `Contract\PendingDeliveries`, which lists those holders; otherwise the call throws `UnsupportedStore`.

`deliverPending()` does not let one holder hold the others back: when a handler throws for a holder, the next holders
are told all the same, and the run ends with a `DeliveryFailed` that carries how many completions were delivered, how
many holders failed out of how many were told, and, as its previous exception, the first exception a handler threw.

A holder whose delivery failed is postponed by the `DeliveryBackoff` given to `Sets`: the next runs pass it by until
its delay is over, and the holders behind it are told in its place. The delay is one minute after a first failure,
doubled after each failure that follows, and never above one day; a delivery that succeeds forgets the failures. From
the third failure in a row, each failure is also told by an `Event\DeliveryKeepsFailing` event, which carries the
holder, the failures, the moment of the next attempt and what the handler threw, for the application to log or alert
someone; a listener of that event that throws does not stop the run.

```php
use Tilttek\Sets\DeliveryBackoff;

new Sets($definitions, $completions, $clock, $dispatcher, new DeliveryBackoff(
    firstDelay: 30,       // seconds after a first failure
    longestDelay: 3_600,  // never longer
    reportAfter: 5,       // DeliveryKeepsFailing from the fifth failure in a row
));
```

`record()` and `deliver()` tell a holder at once, postponed or not. See [Resuming deliveries](installation.md#resuming-deliveries).

A completion is therefore told **at least once**, not exactly once: a handler that granted the rewards and failed
afterwards, or two processes delivering the same completion at the same moment, tell it twice. **The handlers are
idempotent**: they grant the rewards of a completion once, keyed by `Completion::key()` — the set, the tier and the
holder —, for instance under a unique key of the application's own store. Without a dispatcher, a completion is marked
delivered as soon as it is recorded.

| Of `Progress` | Gives |
|---|---|
| `$evaluation` | The evaluation against the latest version |
| `$completions`, `$recorded` | Every completion of the holder in the set, and those this call wrote |
| `completion()`, `completedOnce()` | The completion of the set itself, under any version |
| `isComplete()` | Every member of the latest version owned right now |
| `completedUnderEarlierVersion()` | Completed once, under an earlier version, and not complete under the latest |
| `tierReachedOnce($code)` | The tier reached once, under any version |

## Versions and completions

A completion is never taken back: when a set gains members in a new version, a holder who completed the earlier
version keeps their completion and is not told again when they complete the new one. `completedUnderEarlierVersion()`
lets the application show the completion and the new members side by side.

## Holders

A holder is a `Phil\Resource\ResourceReference` the application gives — `member:1042`, `team:7` —, a stable
identifier: an identifier with `@`, plain or percent-encoded, is refused, so that an e-mail address never becomes the
key of a completion.

## Erasure

`Sets::eraseHolder($holder)` removes every completion of a holder, for the erasure of a member, and returns how many.
The holdings live in the application's stores, which erase them.

## Concurrency

Two processes that record the same completion at the same moment both call `CompletionStore::add()`; the store adds
one and returns false to the other. Both may still deliver it while it is undelivered, which is why the handlers are
idempotent. A database store gives this by a unique key on the set, the
tier and the holder.
