# Changelog

All notable changes to `tilttek/dice-probability` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-12

### Fixed

- The work limit covers the whole exact computation: an operation on weights of many limbs counts as long as its
  arithmetic takes, and a fraction brought to its lowest terms the cube of its size. A short expression such as
  `100d6!` held the processor for about a minute before giving way to an estimate; at the default limit, an exact
  computation now stops after about a second.
- An estimate is bounded too: `Settings` takes a `rollLimit`, 200 000 dice over all the samples by default, past which
  `CannotCompute` says that the estimate would roll too many dice, rather than run for tens of seconds.
- `Natural::divide()` starts the digits of the quotient where the divisor ends: the greatest common divisor of two
  numbers of a hundred limbs takes a second rather than a minute.

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
