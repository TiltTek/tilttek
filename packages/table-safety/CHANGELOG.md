# Changelog

All notable changes to `tilttek/table-safety` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-14

### Changed

- `SignalStore` gains `lastCheckIn()`: the table's last check-in followed by every answer raised after it, with no
  limit. `TableSafety::checkIn()` counts through it, so that a check-in followed by more than 100 signals is still
  counted; a store written for 0.1 implements it. `recent()` and its limit of 100 remain for the screens.
- `FeedbackSummary` holds answers only in whole groups of a threshold of participants, 3 by default, taken in the order
  they were given: under the threshold it holds nothing, `respondents` is 0 and `hasEnoughAnswers()` is false; past it,
  the answers of a group that is not full wait for the group to fill. `FeedbackSummary::of()` takes the threshold, and
  `TableSafety` takes it as `feedbackThreshold`; a threshold under 1 is an `InvalidAnswer`.

### Added

- `FeedbackSummary::$threshold`, `FeedbackSummary::hasEnoughAnswers()`, `FeedbackSummary::assertThreshold()` and
  `FeedbackSummary::DEFAULT_THRESHOLD`.
- The privacy documentation of the summary of stars and wishes: the threshold and what it does not hide.

## 0.1.0-alpha - 2026-11-13

### Added

- `LinesAndVeils` and `ConsentChecklist`: a participant's lines and veils and green, yellow and red levels by theme,
  on the themes of `phil/content-warnings`, immutable and bounded, stored as arrays, and the warning preferences they
  stand for.
- `TableThemes`: the themes of a catalogue and those a table adds, with their labels.
- `TableAgreement`: the strictest boundary and level of each theme of a table, without saying who gave them, and the
  warning preferences of the table.
- Session zero: `Question` of three kinds, `Template` with a game master's own questions, the four built-in
  `Templates` — one-shot, campaign, convention, online —, `Answers` and the anonymous `Pact`.
- Signals: `SignalKind` for the X-card, Script Change, the open door and check-ins, `Audience`, `Signal`, anonymous by
  default and seen by the table or the game master alone, and `CheckInTally`.
- `StarsAndWishes`, `Feedback` and the anonymous `FeedbackSummary` of a session.
- `TableSettings` and `SafetyTool`, with the people each tool is credited to.
- The contracts `TableStore`, `ParticipationStore`, `SignalStore`, `FeedbackStore` and `SignalObserver`, and the
  memory stores.
- `TableSafety`: the service of the tools, with a participant's export and erasure.
- The exceptions `TableSafetyException`, `InvalidAnswer`, `ToolNotEnabled` and `NotGameMaster`.
- The documentation in `doc/`, one file per topic, with the credits of the tools, their sources and the licences of
  their texts, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
