# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/game-night` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `GameNight`: a night of a club or a store, when and where, its host, its tables, its sign-ups, its waiting list,
  its games proposed and its votes, checked whole at each change.
- `Table`: a table and its seats, 1 to 30, with the game planned at it and the member who leads it.
- `Registration`: a member signed up, alone or with one guest who takes a place, confirmed or waiting.
- The waiting list, served in the order members signed up: freed places go to the first member waiting whose places
  fit, and nobody passes them.
- `GameNights`: planning, tables, publication, sign-ups, withdrawals that free places, seats, the vote and
  cancellation; sign-ups, seats and votes close when the night starts.
- The events `NightPublished`, `NightFull`, `NightCancelled` and `SeatOffered`, by identifiers only, told once to a
  `Contract\NightObserver`; no reminder is sent.
- `Choice\GameChooser` and `Choice\Criteria`: games suggested for a table or the whole night, among the games the
  members can bring, read through `Contract\ShelfReader`, and those the club or the store keeps, for the players and
  the minutes there are.
- `Choice\Suggestion`: a game suggested, with the choice of the members of the catalogue for this number of players
  and the number of votes it rests on, and the link to its page on BoardGameGeek.
- `Vote\VoteResult`: one vote per member signed up, counted and explained, with a tie left to the host.
- `NightPlays` and `Contract\PlayJournal`: the games played at a night sent to the play log.
- `Contract\NightStore`, kept at a version, and `Store\InMemoryNightStore` for tests.
- The exceptions `InvalidNight`, `NightRefused`, `NightConflict` and `NightNotFound`, under `GameNightException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
