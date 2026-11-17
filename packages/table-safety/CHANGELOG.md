# Changelog

All notable changes to `tilttek/table-safety` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-15

The next version is 0.3.0-alpha: what the pact and the agreement show to people changes.

### Changed

- `SessionZero\Pact` holds answers only in whole groups of a threshold of participants, 3 by default, taken in the
  order they were given: under the threshold every tally is at 0, every list of texts is empty, `respondents` is 0 and
  `hasEnoughAnswers()` is false; past it, the answers of a group that is not full wait for the group to fill.
  `Pact::of()` takes the threshold; a threshold under 1 is an `InvalidAnswer`.
- `TableAgreement::$respondents` counts the answers of the summary shown to people, in whole groups of the same
  threshold, rather than every participant who answered. `lines()`, `veils()`, `boundary()`, `consent()`,
  `checklist()`, `themesAt()` and `preferences()` still apply every answer, under the threshold too, so that a line or
  a veil protects the table from the first answer. `TableAgreement::of()` takes the threshold.
- `TableSafety` takes the threshold of the agreement and the pact as `answersThreshold`, and gives them the table's
  participations in the order of their first answer, so that a new participant waits for their group and a
  participant who changes their answers keeps their place.
- `TableSafety::export()` gives each participation's `first_answered_at`.
- `FeedbackSummary::DEFAULT_THRESHOLD` and `FeedbackSummary::assertThreshold()` are those of `RespondentThreshold`.

### Added

- `AgreementSummary`: the agreement shown to people, from `TableAgreement::shown()`.
- `TableAgreement::$threshold`, `TableAgreement::shown()` and `TableAgreement::hasEnoughAnswers()`.
- `Pact::$threshold` and `Pact::hasEnoughAnswers()`.
- `RespondentThreshold`: the default threshold of 3, its check and the count of whole groups.
- `Participation::$firstAnsweredAt`: the moment of the participant's first answer at the table, kept when they change
  their answers, and `Participation::inOrderOfFirstAnswer()`. The constructor takes it last; when it is not given, it
  is that of `$updatedAt`. A `ParticipationStore` keeps it and gives it back.
- The documentation of the threshold of the agreement and the pact, with what protects the table from every answer
  and what the threshold does not hide.

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
