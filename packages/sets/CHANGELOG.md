# Changelog

All notable changes to `tilttek/sets` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.1-alpha: additions only, `Contract\CompletionStore` unchanged.

### Added

- `Sets::deliverPending($limit)`: the undelivered completions told again for the holders waiting the longest, at most
  1 to 10 000 holders, without knowing which holders are waiting; a holder whose handler throws holds no one back, and
  the run ends with `Exception\DeliveryFailed`, which carries the counts and the first exception thrown.
- `Contract\PendingDeliveries`, optional beside `Contract\CompletionStore`: the holders with undelivered completions,
  the one waiting the longest first; `Store\InMemoryCompletionStore` implements it. `Exception\UnsupportedStore` says
  that a completion store does not.
- An optional Symfony integration under `Tilttek\Sets\Symfony`, on suggested packages and without a bundle: the
  `tilttek:sets:deliver` command, with `--limit` and `--holder`, the `DeliverPendingCompletions` message, which carries
  a limit only, and its handler, and the `tilttek_sets` schedule, which sends it every five minutes by default, with an
  interval, a limit and a lock of the application's.
- Resuming deliveries with Symfony documented: registering the services, the command, the schedule and its worker.

## 0.2.0-alpha - 2026-11-14

### Changed

- A completion's event can no longer be lost when a handler fails. The store keeps whether each completion was
  delivered: `CompletionStore` gains `undelivered()` and `markDelivered()`, which a store written for 0.1 implements.
  `Sets::record()` tells every undelivered completion of the holder in the set, with the rewards of the version it was
  earned under, and marks each delivered once its event was dispatched without an exception; a handler that throws
  leaves its completion undelivered, the others are told all the same, and the first exception is thrown again at the
  end. A completion is told at least once: the handlers are idempotent.

### Added

- `Sets::deliver()`: every undelivered completion of a holder told again, in every set, to resume after a failure.
- The delivery of the completions documented: at-least-once delivery, idempotent handlers and resuming.

## 0.1.0-alpha - 2026-11-13

### Added

- `SetDefinition`: a version of a set, 1 to 1 000 000, with 1 to 1 000 members named once by a stable identifier in
  the order they are shown, up to 20 tiers checked from the easiest to the hardest, and up to 20 rewards; read from
  and written to arrays and JSON of at most 1 MiB, every fault an `InvalidSet`.
- `Tier`: a number of members owned at a level of 1 to 1 000 or higher — every member by default —, with up to 20
  rewards, so that a set redone at higher levels is one set with one tier per level.
- `Reward`: an opaque reward a set or a tier declares — a kind, a reference and a quantity —, granted by the
  application.
- `Holdings` and `Evaluation`: up to 100 000 members a holder owns, each at its highest level, measured against a set —
  the members owned and missing in the set's order, the percentage rounded down, the tiers reached and the next one.
- `Sets`: versions defined, never rewritten, and defined again without a word when unchanged; a holder's progress
  against the latest version; the completion of the set and of its tiers recorded once with the version they were
  earned under and their moment in UTC, never taken back by a later version; the erasure of a holder.
- `Progress`: the evaluation with the holder's completions, those this call recorded, and whether a completion was
  earned under an earlier version that the holder has not completed since.
- `Event\SetCompleted` and `Event\TierReached`: dispatched once per completion, with the rewards declared.
- `Identifiers`: the shapes of codes and members, and holders referenced by a stable identifier, never by an e-mail
  address.
- `Contract\DefinitionStore`, `Contract\CompletionStore`, `Store\InMemoryDefinitionStore` and
  `Store\InMemoryCompletionStore`: the ports of the stores and their adapters in memory.
- `UtcClock`: the system's clock in UTC, the default clock.
- The fuzz target of a definition and the benchmarks of the largest set.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
