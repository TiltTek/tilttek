# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/sets-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

The first version is 0.1.0-alpha, not yet published.

### Added

- `TilttekSetsBundle` and its configuration: the services of the stores of the definitions and of the completions,
  in memory when none is named; the clock; the backoff of a holder whose delivery failed — first delay, longest delay,
  failures reported —; and the schedule — interval, limit, lock —, which can be disabled.
- The `Sets` service, public, on the application's event dispatcher and the configured backoff.
- The `tilttek:sets:deliver` command: the completions not delivered of the holders waiting the longest, at most
  `--limit`, or every one of the holder `--holder` names; it fails, saying how many were delivered, when a handler
  threw. Registered when `symfony/console` is installed.
- The `DeliverPendingCompletions` message, which carries a limit only, and its handler. Registered when
  `symfony/messenger` is installed.
- The `tilttek_sets` schedule, which sends that message every five minutes by default, runs a missed run once and
  takes an optional lock. Registered when `symfony/scheduler` is installed.
