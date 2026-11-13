# Changelog

All notable changes to `tilttek/sets` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
