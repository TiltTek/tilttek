# Changelog

All notable changes to `tilttek/secret-rolls` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `SecretRolls`: hidden rolls scene by scene, rolled with `tilttek/dice` from a seed of 32 bytes drawn from a secure
  source, the roll's identifier — derived from its scene and its position — as the client seed; the opening sealed in
  the store and its commitment appended to the scene's public journal; reveals in the order given, each roll once,
  nothing appended when one roll is refused; the closing of a scene with a partial, empty or total reveal, the rolls
  not revealed staying withheld and revealable later; a scene full at 4 999 entries.
- `HiddenRoll`, `RollOpening` and `RollCommitment`: the sealed roll with its roller and time, its opening — expression
  in canonical form, rounding, seed, result and context —, and the SHA-256 of the canonical JSON of the opening,
  compared in constant time; openings and hidden rolls written and read back as plain values, a field not known
  refused.
- `RollVerifier`: an opening checked against the commitment published, its expression read within the limits of the
  dice and in canonical form, and rolled again from its seed to the result revealed; `verifyPublished()` for data as
  published.
- `Journal\SceneJournal`, `Journal\JournalEntry` and `Journal\EntryKind`: the public journal of a scene, each entry
  chained to the one before by its SHA-256; its state, its revealed openings and its withheld rolls; `problems()`, every
  fault of a journal in a sentence; the JSON export a player downloads and checks alone, read back within 32 MiB and 64
  levels.
- `Contract\SecretRollStore` and `Contract\JournalPublisher`, with `Store\InMemorySecretRollStore` and
  `Store\NoPublisher`: the journals and the sealed rolls kept by the application, an entry out of place refused as a
  concurrent write, each entry pushed to the table.
- `Exception\SecretRollsException`, with `InvalidSecretRoll`, `SecretRollRefused` and `JournalConflict`.
- Fuzz targets for the verification of an opening and for a published journal, and benchmarks of a roll, a
  verification and the check of a scene of 500 rolls.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
