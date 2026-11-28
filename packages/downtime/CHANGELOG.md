# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/downtime` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Activity`: an activity the rules of a system declare for the time between sessions — 1 to 365 days, up to 8 costs,
  1 to 16 outcomes and a resolution —, checked as a whole, with `totals()` over its days.
- `Cost` and `CostBasis`: what an activity costs in one resource, 1 to 1 000 000, paid once or for each day.
- `Outcome` and `Effect`: a result with its label, its text and up to 8 gains or losses in the resources the system
  names, each resource once; texts valid UTF-8, trimmed, without control characters in a line nor direction overrides
  anywhere.
- `Resolution` and `Band`: an activity ends with a fixed outcome, or with the outcome of the band its total falls in;
  1 to 16 bands without overlap nor gap, a total beyond them falling in the nearest; the dice expression kept as the
  system writes it, bounded in shape, for the roller to read.
- `SystemRules`: the 1 to 64 activities of one game system, each key once.
- `Entry` and `Stage`: one activity in the log of a character, kept whole as it was declared, with the days spent, the
  roll and the outcome, from queued through awaiting its roll and resolved to confirmed or cancelled; values checked
  against the stage, each change returning a new value.
- `Downtime`: a player or the game master chooses an activity, up to 8 open a character; the game master advances a
  campaign by 1 to 365 days, spent on each character's queue in the order chosen, the days one entry does not need
  going to the next, every change kept at once or none; a roll recorded from the table, or rolled by a roller; the game
  master confirms the outcome or names another, and cancels an open entry, a player theirs while queued; a log read in
  the order chosen, up to 1000; it keeps no state between calls.
- `Contract\Roster`, `Contract\Roller` and `Contract\RuleBook`: who runs a campaign and who plays a character, the total
  of a dice expression, and the activities of each system; `Rules\InMemoryRuleBook` over the rules declared once.
- The events `ActivityChosen`, `ActivityProgressed`, `ActivityCompleted`, `ActivityResolved`, `ActivityConfirmed` and
  `ActivityCancelled`, by identifiers only, told once to a `Contract\DowntimeObserver`.
- `Contract\EntryStore`, kept at a version all or none and read in the order chosen by character, by open entries and by
  queued entries of a campaign, and `Store\InMemoryEntryStore` for tests.
- The exceptions `InvalidDowntime`, `UnknownActivity`, `DowntimeRefused`, `EntryNotFound` and `DowntimeConflict`, under
  `DowntimeException`.
- Benchmarks of the largest campaign advanced by a day, and of the largest rules checked whole.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
