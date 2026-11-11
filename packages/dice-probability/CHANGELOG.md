# Changelog

All notable changes to `tilttek/dice-probability` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Probabilities`: the exact distribution of an expression of `tilttek/dice`, two expressions compared, and the
  probability of an event on a set of dice, on the custom dice and limits of the caller, with the rounding of a roll.
- `Distribution` and `Outcome`: every value and its weight over the ways to roll; probability, at least, at most, a
  validator's probability, mean and variance as exact fractions, standard deviation.
- The exact computation: arithmetic and functions over independent outcomes, plain dice summed by a sliding window and
  others by squaring, rerolls repeated or once, explosions spread, compound or penetrating with their limit, floors,
  ceilings, transformations, filters, keep and drop over the sorted multisets of the dice, successes and failures,
  judgements of the whole set, counts, sides and explosion limits rolled first, lists of values; texts, lists and
  `repeat()` refused, having no total.
- Events: `Event`, `Faces`, `Count`, `OfAKind`, `Straight`, `AllEqual`, `AllDifferent`, `AllMatch`, `AnyOf`, `AllOf`
  and `Not`, computed over the sorted multisets of the dice, rerolls followed; `Chance`.
- `Versus`: the probability that the first expression is higher, that both are equal, that the second is higher.
- `Settings`: explosions followed to a depth with the probability that remains; past a work limit, or for the
  constructs the computation does not follow, a seeded estimate with `Estimate`, its `EstimateReason` and its margin.
- `Natural` and `Fraction`: exact numbers of any size, without extension.
- `ProbabilityException` and `CannotCompute`.
- Documentation: installation, distributions, events, comparing, explosions and estimates, exact numbers, exceptions,
  extension points, testing and reference.
- Community files: contribution guide, code of conduct, security policy and MIT licence.
