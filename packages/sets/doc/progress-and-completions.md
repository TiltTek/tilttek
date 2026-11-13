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
dispatching, for each one recorded:

- `Event\SetCompleted`, with the `Completion` and the rewards of the set;
- `Event\TierReached`, with the `Completion` of the tier and its rewards.

A `Completion` holds the set, the holder, the tier or null for the set itself, the version of the set it was earned
under, and its moment in UTC. The store keeps one per set, tier and holder, so a completion is told once: a holder who
trades a member away and gets it back is told nothing more. The application calls `record()` when a holder's holdings
change — a badge earned, an item received —, and grants the rewards when it hears the events.

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
one and returns false to the other, which tells nothing. A database store gives this by a unique key on the set, the
tier and the holder.
