# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/conditions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `ConditionDefinition` and `Stacking`: a condition of the game system, declared by the site, that a new application
  replaces, raises to the highest value, sums up to its highest value, joins as a separate instance up to 16, or leaves
  as it is; a value of up to 100, up to 32 modifiers and up to 16 conditions it brings with it.
- `ConditionCatalogue`: up to 1,000 conditions, each identifier once; a condition brings only conditions of the
  catalogue, without a value, and never brings itself back through others.
- `Duration`, `DurationKind`, `Boundary` and `Anchor`: a duration of 1 to 10,000 rounds counted at the start or the end
  of the target's turns, of the source's turns or of the rounds; of up to a hundred years of game time; of one second to
  366 days of real time; or until removed.
- `Decay`: a value that falls by an amount at the start or the end of the affected character's own turns, the condition
  ending at zero.
- `Modifier` and `Operation`: an addition, a multiplication of 0 to 1,000 percent, a floor, a cap or an override of a
  statistic, by the condition's value when asked; additions of one type keep only their highest bonus and lowest
  penalty.
- `Effect\EffectCalculator`, `Effect\AppliedModifier`, `Effect\StatValue` and `Rounding`: up to 1,000 statistics once the
  modifiers are counted, in the order of the operations, multiplications rounded by the system's rule, values bounded,
  with the modifiers that made the difference.
- `ActiveCondition` and `CharacterConditions`: up to 64 applications on a character, each with its instance identifier,
  value, source, what is left of its duration and its end in real time, kept at a version.
- `Conditions::apply()`: a condition applied by its stacking rule, its value and source checked, its duration given or
  its own; what ran out in real time ends first.
- `Conditions::remove()`, `removeCondition()` and `setValue()`: one application removed, every application of a
  condition removed, a value changed or set to zero to remove it.
- `Conditions::startTurn()`, `endTurn()`, `startRound()`, `endRound()` and `advanceTime()`: durations in rounds and game
  time run down, values decayed, for up to 200 participants, each boundary counted once by its tick identifier, a
  participant without change left unwritten.
- `Conditions::expire()`: the conditions past their end in real time ended, for a scheduled task.
- `Conditions::has()`, `modifiers()` and `effective()`: whether a character has a condition, applied or brought by
  another; the modifiers in force; the statistics once they are counted; what ran out in real time left out before it
  is ended.
- `View\ConditionsView` and `View\ConditionState`: the applications, then the conditions they bring with the ones that
  bring them, and the next end in real time, as plain data.
- The events `ConditionApplied`, `ConditionChanged` and `ConditionEnded` with its `EndReason`, by identifiers only, told
  once to a `Contract\ConditionObserver`.
- `Contract\ConditionStore`, kept at a version, and `Store\InMemoryConditionStore` for tests.
- The exceptions `InvalidCondition`, `ConditionRefused`, `ConditionNotFound` and `ConditionConflict`, under
  `ConditionsException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
