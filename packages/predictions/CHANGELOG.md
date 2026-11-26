# Changelog

All notable changes to `tilttek/predictions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Predictions`: questions on dated outcomes of events, asked under the application's key, the same question asked
  again returned and one asked differently refused; members' picks, changed until the deadline; nothing staked.
- `Question`: 2 to 32 outcomes that hold no colon, so that a member's reference is never one, a deadline in UTC, 1 to
  1 000 000 points, a scoring and a status.
- `Scoring::Choice`, the question's points for a right pick, and `Scoring::Probability`, a share of them by the Brier
  score of the forecast; `Brier`, in whole numbers, rounded half up.
- `Pick`: one outcome, or whole percents summing to 100, an outcome left out given 0 %.
- The deadline: predictions closed at it, and checked again by the store as it writes, so that none is written once
  the outcome is declared.
- `resolve()` and `settle()`: each prediction's points granted through `tilttek/points` under a key built from its
  random identifier, so that a settlement run again, or by two processes at once, pays nothing twice; predictions read
  in pages of their identifiers; `Settlement`.
- `cancel()`: a question closed without any points, which can no longer be resolved.
- Reading: `question()`, `predictionOf()` and `predictionsOf()`, at most 100 at once.
- Erasing a member: their predictions deleted.
- `Contract\PredictionStore` with `Store\InMemoryPredictionStore`; `QuestionKey`, `PredictionId`, `Prediction` and
  `QuestionStatus`.
- Exceptions under one `PredictionsException` interface: `QuestionClosed`, `UnknownQuestion` and
  `InvalidPrediction`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
