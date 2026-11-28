# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/celebrations` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Achievement`: what a member earned, as the application's listener hands it in — the member's reference, the source
  and the kind by keys, what was earned and the occurrence that makes it unique (1 to 191 visible ASCII characters),
  the moment it was earned, at most 10 parameters for its message, and maybe the moment after which it is no longer
  shown.
- `Celebrations`: receiving an achievement once per member, source and occurrence, an achievement told again returned
  as kept and not told; withdrawing a reward taken back, harmless when told twice; the pending entries in groups of one
  source and one kind, the earliest earned first, ties by reception; the count for the bell; the next celebration;
  marking seen by sequence, by group or all at once; a history read by pages of 1 to 100 entries, the last received
  first; the members' preferences; erasing a member's inbox and preferences.
- `Entry` and `EntryState`: an entry's sequence, never reused for a member, its state — pending, seen, withdrawn or
  lapsed — and its received and settled moments; a pending entry lapses when its moment to be shown comes, while the
  reward stands.
- `Group` and `Celebration`: the first entries of a group, as many as the limits allow, with its count; what the window
  shows next, with its motion, its sound and the groups remaining.
- `Preferences` and `Motion`: a window on by default and turned off by the member while the inbox stays, full, reduced
  or no motion, a sound off by default; a device that asks for reduced motion reduces full motion and never adds any.
- `Limits`: pending entries per member, 200 by default, beyond which the earliest earned lapse; entries per member,
  500 by default, beyond which the first received of the settled entries are dropped; entries shown per group, 10 by
  default.
- `Contract\CelebrationStore`, with `Store\InMemoryCelebrationStore`.
- `Contract\CelebrationObserver`, told `CelebrationChanged` with its `Change`, identifiers only.
- `Exception\InvalidCelebration`, under the `Exception\CelebrationException` interface.
- A bench of the fullest inbox the default limits allow: the count, the next celebration, an achievement received at
  the bound.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
