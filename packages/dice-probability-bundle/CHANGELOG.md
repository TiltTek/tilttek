# Changelog

All notable changes to `tilttek/dice-probability-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-19

### Added

- `TilttekDiceProbabilityBundle` and its configuration: explosion depth, work limit, samples, seed and roll limit of
  the estimates, the most dice of an expression and values of a distribution, rounding of divisions, subdirectory of
  the UX kit's recipes, work of the search of groups, and rate limit of the component; `TilttekDiceBundle` required,
  whose custom dice and limits the probabilities use. The defaults hold a request to a fraction of a second.
- `DiceStatistics`: the distribution of an expression written as text, two expressions compared, an event on a set of
  dice, in the notation of a locale, with the configured rounding or another; `Rounding`; `TooManyOutcomes` for a
  distribution of more values than the bundle draws.
- `GroupSearch`: the steps of the search of the groups an estimate looks for, `6d6g10`, weighed before any die is
  rolled — exhaustive up to 12 dice, greedy beyond, the worst count for a computed or exploding set, `repeat()` and
  every branch counted —, and `GroupsTooCostly` past `group_work_limit`: `12d6g12` and `10d10g10` are refused at once.
- `Refusal`, implemented by `GroupsTooCostly`, `TooManyOutcomes` and `Throttled`: the key and the parameters of the
  reason of a refusal in the `tilttek_dice_probability` domain, translated in English and French.
- Twig: `tilttek_dice_distribution()`, `tilttek_dice_versus()`, `tilttek_dice_probabilities()`, the
  `tilttek_dice_fraction` filter, whose reduction is bounded — terms of up to 18 digits on native integers, longer ones
  by `ext-gmp` or not at all, `digits` between 1 and 60 —, and `tilttek_dice_probability_kit()`; the templates of a
  summary, a bar chart hidden from assistive technologies and a table that is always there, with the comparison of two
  expressions, the estimates and their margin, and the share of explosions cut at their depth, in cards of the UX kit;
  translations in English and French.
- The Live Component `TilttekDiceProbability:Distribution`: an expression, a second one to compare it with, and what
  the chart shows, computed again as they are typed, in fields of the UX kit, the results announced politely and the
  errors said in its alert, a refusal of the bundle in its translated reason.
- The rate limit of the Live Component, on by default: `ComputationThrottle` counts each computation the browser asks
  for, 60 a minute per IPv4 address or IPv6 /64 in a sliding window of `symfony/rate-limiter`, keyed by a digest,
  failing closed; a cache pool or a factory of the application; the container refuses to compile without
  `symfony/rate-limiter` unless `rate_limit` is turned off.
- The screens drawn with the recipes `Alert`, `Card`, `Chart`, `Field`, `Input`, `RadioGroup` and `Table` of the PHIL
  UX kit, installed by the Symfony UX Toolkit, named in the subdirectory of its configuration or of
  `kit_subdirectory`: `KitComponents` and `KitSubdirectoryPass`.
- `group_work_limit` given to the `Settings` of `tilttek/dice-probability` 0.1.4 as well: its estimate counts the steps
  of the groups it actually searches and stops past the same limit, behind the weighing of `GroupSearch`.
- The rate limit of the Live Component refuses, in the `prod` environment, a cache pool local to each server, where a
  client could spend the limit once per server: `rate_limit.cache_pool` names a pool on a shared store, or
  `rate_limit.single_server: true` says the application runs on one server. The check is the `RateLimitGuard` of
  `phil/rate-limit`.
- The Flex recipe in `recipe/0.1`: the bundle and `config/packages/tilttek_dice_probability.yaml`, with the rounding
  and the rate limit of the component.
- Documentation: installation, configuration, statistics in PHP, templates, extension points, testing and reference.
- Community files: contribution guide, code of conduct, security policy and MIT licence.
