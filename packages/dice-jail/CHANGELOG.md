# Changelog

All notable changes to `tilttek/dice-jail` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-19

### Added

- `Jail\Warden`, `Jail\Inmate`, `Jail\JailEvent`, `Jail\EventKind` and `Jail\Evidence`: a member's die jailed for a
  sentence that ends by itself or until released, once at a time, at most 1 000 per keeper; releases; the dice in jail
  in the order they went in; the history of each die; the statistics shown kept with the jailing; a keeper refused
  when written as an email address.
- `Statistics\GoodnessOfFit`, `Statistics\FitResult`, `Statistics\FaceCount` and `Statistics\Verdict`: Pearson's
  chi-squared test of one die against a fair one, trusted from five expected rolls per face, its p-value from the
  regularized incomplete gamma function with Γ exact, and four verdicts — not enough rolls, nothing unusual, somewhat
  unusual below 1 %, very unusual below 0.1 % —, none of which calls a die loaded; the observed and expected means.
- `Statistics\Tally` and `Statistics\ExpectedFaces`: faces counted, from values or from the rolled dice of
  `tilttek/dice`, up to 1 000 000 rolls; fair dice from a range, or from an exact distribution of
  `tilttek/dice-probability`, estimated and cut ones refused; the minimum of rolls in whole rolls.
- `Statistics\Explanation`, `Statistics\Language` and `Statistics\Origin`: the verdict in plain English and French,
  with each language's typography, the average roll, advice to look at a physical die or to test new rolls, and for
  digital dice the reminder that the generator serves every die alike.
- `Statistics\DieAssessor` and `Contract\RollSource`, with `Store\InMemoryRollSource`: the test from recorded rolls,
  from a moment on.
- `Contract\JailStore`, with `Store\InMemoryJailStore`: the histories, each event at its position, a concurrent write
  refused with `Exception\JailConflict`.
- Exceptions under one `DiceJailException` interface: `InvalidRolls`, `InvalidJailEntry`, `JailRefused`,
  `JailConflict`.
- A fuzz target for the test, and benchmarks of a d20 judged and a d100 tested.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
