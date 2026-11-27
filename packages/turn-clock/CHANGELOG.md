# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/turn-clock` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `TimeControl`, `Mode` and `Overtime`: a budget of 1 second to 31 days, an increment of up to 1 day added after each
  turn (Fischer), given back up to the time spent (Bronstein) or waited before the budget runs (a delay), or the whole
  budget again at each turn, and the time over counted below zero or forfeited; `TimeControl::daysPerTurn()` for a game
  by correspondence.
- `TableClock`, `PlayerClock` and `Vacation`: a clock of 1 to 20 players in seat order, each with their budget, what is
  left, their turns, the time spent and up to 8 vacations of at most 60 days, bounded and checked.
- `TurnClock`: a clock created with a budget per player, started, and read from a PSR-20 clock; it keeps no state between
  calls.
- `TurnClock::pass()`: the turn ended by naming the player holding it, the time charged and the increment given, the next
  turn to the player named or the next one in seat order not out of time, the clock stopped when every player is out.
- `TurnClock::check()`: the expiry of the turn in progress told once in overtime, or the turn passed on when the clock
  forfeits.
- `TurnClock::pause()` and `resume()`: the whole table's clock stopped and run again, a turn passed while paused starting
  paused.
- `TurnClock::declareVacation()`: a player's vacation, during which their clock does not run, counted from the moment it
  is declared when already begun.
- `TurnClock::adjust()` and `stop()`: time added or taken away, and the clock stopped with the turn in progress charged.
- `View\ClockView` and `View\PlayerTime`: each player's time at a moment, and when the turn in progress runs out of time,
  the vacations to come skipped, as plain data.
- The events `ClockStarted`, `TurnStarted`, `TurnEnded`, `TimeExpired`, `ClockPaused`, `ClockResumed`,
  `VacationDeclared`, `TimeAdjusted` and `ClockStopped`, by identifiers only, told once to a `Contract\ClockObserver`.
- `Contract\ClockStore`, kept at a version, and `Store\InMemoryClockStore` for tests.
- The exceptions `InvalidClock`, `ClockRefused`, `ClockNotFound` and `ClockConflict`, under `TurnClockException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
