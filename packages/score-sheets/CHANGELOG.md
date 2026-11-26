# Changelog

All notable changes to `tilttek/score-sheets` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `SheetDefinition`, `Category` and `Tiebreaker`: the sheet of a game declared as data — 1 to 64 categories, each
  entered between its bounds or computed from those before it, counted in the total or not, up to 8 tie-breakers and
  the way teams combine —, read from JSON of at most 64 KiB and written back.
- The operations `Sum`, `Product`, `Majority` — points by place, ties sharing their place — and `Bonus`, and
  `Operations`, which reads them back; integer results that refuse to overflow.
- `Entries` and `Team`: 1 to 100 participants, up to 50 teams, and values entered, corrected or erased.
- `Scoring\Scorer`: the scoreboard computed at each entry — totals of the categories that count, ranks by total then
  tie-breakers, shared ranks, team scores by sum or best — with one explaining `Step` by category.
- `ScoreKeeper`, `MatchSheet` and `SheetStatus`: a match's sheet prepared out of the players' sight, published, filled
  against its version, closed, and reopened to correct a score entered too early, each closing a new revision.
- `MatchResult`, `Placement` and `Message\MatchResultRecorded`: the neutral result of a closed match for a play log or
  a rating, and its message with the match and the revision only.
- `BlankSheet`: the blank sheet to print, as neutral data.
- `Contract\MatchSheetStore` and `Store\InMemoryMatchSheetStore`: the store port, written over the previous version
  only, and the store in memory for tests.
- Exceptions under one `ScoreSheetsException` interface: `InvalidSheet`, `InvalidEntry`, `IllegalTransition`,
  `UnknownMatch` and `ConcurrentChange`.
- Property tests over random entries and a fuzz target of the declarations.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
