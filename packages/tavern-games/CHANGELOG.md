# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/tavern-games` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Contract\TavernGame` and `Turns`: turn-based games of `tilttek/game-engine` that keep their own turns in their data
  and accept the timeout of a turn, `tavern.timeout`, made by the table.
- `TavernTables`: tables opened and played, the turn timer told whose turn starts or that the game is over, the timeout
  of a turn played only when the seat still plays after the same number of moves, views, the whole state and endings.
- `Contract\TurnTimer`, `Store\NullTurnTimer` and `Store\InMemoryTurnTimer`: the optional clock of the turns, no clock
  by default, and the running turns in memory for tests.
- `Ending` and `Tone`: a victory, alone or shared, celebrated; any other ending told kindly.
- `TavernGames`: the tavern games of an application, by name, and the same games for the engine's `Tables`.
- `DiceCup`: one to ten six-sided dice rolled by `tilttek/dice` with the chance of a move.
- `Odds\Estimator`, `Odds\Estimate`, `Odds\Trial` and `Contract\Simulation`: the odds of a position over 1 to 100 000
  simulated endings, wins, ties and losses counted apart with the number of endings.
- `Game\Pig`: the dice game of push-your-luck, to a target of 20 to 200 points.
- `Game\ShipCaptainCrew` and `Odds\CargoChase`: the dice game of the ship, the captain, the crew and the cargo, with
  shared wins on equal cargoes, and the odds of the turn of the seat playing.
- `Game\CrazyEights`: the card game of shedding, eights wild, its cards kept by `tilttek/card-deck` and shuffled from a
  seed drawn from the table's, each seat seeing only its own hand; blocked and over-long games won by the fewest cards.
- Exceptions under one `TavernGamesException` interface: `InvalidTavernGame`.
- A fuzz target of the moves of Crazy Eights and a benchmark of its replay.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
