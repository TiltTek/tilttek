# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/play-log` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Play`: a game played once, its game of the catalogue, the member who recorded it, 1 to 100 seats, its result, and
  when known its date, minutes, group and place, bounded and checked; a date may stay unknown.
- `Seat`: a member or an anonymous player, with a score and whether they won; `Seat::members()` and `Seat::guests()`.
- `Result`: competitive, with the winners marked on the seats, a cooperative win, a cooperative loss, or unfinished.
- `PlayLog`: record, correct and delete a play by the member who recorded it, on a game of the catalogue that is not
  hidden; it keeps no state between calls.
- The seats linked to their members only once confirmed: the recorder's at once, another member's by
  `PlayLog::confirm()` or by a `Contract\PlayerConsent`, made anonymous by `PlayLog::decline()`; `Play::seenBy()`
  shows the seats not confirmed as anonymous to other readers.
- `PlayLog::forget()`: a member forgotten by batches, the plays they recorded deleted and their other seats made
  anonymous.
- `PlayQuery` and `PlayLog::plays()`: the plays of a member, waiting for a member, recorded by a member, or of a group,
  by game and period, the latest first, those whose date is unknown last.
- `PlayStatistics`, `Statistics`, `GameStatistics` and `PlayerStatistics`: the plays, wins, unfinished plays, minutes,
  games, members played with and h-index of a member or a group, computed when asked from at most 10 000 plays.
- The events `PlayRecorded`, `PlayCorrected`, `PlayDeleted`, `SeatLinked`, `SeatPending` and `SeatUnlinked`, by
  identifiers only, told once to a `Contract\PlayObserver`.
- `Contract\PlayStore`, kept at a version and read by query, and `Store\InMemoryPlayStore` for tests.
- The exceptions `InvalidPlay`, `PlayRefused`, `PlayNotFound` and `PlayConflict`, under `PlayLogException`.
- The adapters of a game night's play journal and of the catalogue's play history, documented.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
