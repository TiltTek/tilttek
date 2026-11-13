# Changelog

All notable changes to `tilttek/game-engine` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Contract\Game`, `GameDefinition`, `Phase`, `Transition` and `TurnOrder`: a game's rules behind one pure contract —
  setup, moves allowed, moves played, phases entered, ending and the view of a seat —, and its declaration: phases with
  a free, sequential, initiative or simultaneous turn order and an optional number of moves that ends a turn, the
  transitions between them, the phase it starts in, 1 to 64 seats, its options, the moves a seat makes out of turn,
  whether the last move may be taken back, the version of its rules, the preferences it offers each player and the
  statistics it reports at its end.
- `Phases`: the phases as a state machine of Symfony Workflow without an event dispatcher, so that a replay takes the
  same transitions, checked by its `StateMachineValidator`, with the transitions enabled from a phase and the Mermaid
  flowchart of the machine.
- `Option`, `OptionKind` and `TableOptions`: options that are booleans, whole numbers between bounds or choices among
  texts, with their defaults, resolved for each table by Symfony's OptionsResolver, and read back from a cache.
- `Preferences`: the preferences of a player, options of a seat resolved by the same OptionsResolver and given to the
  view of the seat, never to the rules nor to the log.
- `Statistic`, `StatisticScope` and `Statistics`: the statistics a game declares for the table or for each seat, which
  its outcome reports as whole numbers at its end, checked by the engine after every move.
- `Engine`, `Table`, `GameState`, `Turn` and `Outcome`: tables opened with their seats and a seed of 256 bits from the
  system's secure source, moves checked against the seat and its turn and asked of the game, turns ended by a seat or by
  the number of moves of a phase, simultaneous rounds, initiative merged and ordered from the highest with the order of
  the table for ties, delays after a later seat, phase changes, endings that close the table with their statistics,
  and the view of a seat shaped by its player's preferences; `GameState::toArray()` and `GameState::fromArray()` write
  and read back a state as plain values, every field checked, for a shared cache.
- `GameLog`, `LoggedMove`, `Move` and `Randomness`: the log of a table as its truth — game, version, seed, seats,
  options and up to 100 000 numbered moves, each with whether it drew at random —, written and read back as plain
  values; moves with a typed name and a payload of plain values of 8 levels and 16 384 bytes at most; chance drawn by
  `Random\Engine\Xoshiro256StarStar` from the seed and the place of each step in the log; a replay that rebuilds the
  state and refuses a log of another version, a move that no longer applies, or one that draws differently; the last
  move taken back when the game allows it, the game is not over and the move revealed nothing; the commitment to the
  seed, its SHA-256 digest, which the players may be shown at the opening, and a replay verified against it.
- `LogHeader`, `Step`, `Engine::setup()`, `Engine::advance()`, `Engine::step()` and `Engine::checkUndo()`: a log read
  without its moves, with a revision that counts every change and never goes down, and a state brought forward by the
  moves that follow it, read as they come.
- `Tables`, `Games`, `Contract\LogStore`, `Contract\StateCache`, `Snapshot`, `Contract\TablePublisher` and
  `TableUpdate`: the tables of an application opened, played, taken back and read; the logs appended and taken back
  with the number of moves and the revision the caller read, against concurrent changes; the moves read window by
  window; the log of a finished table shown, seed included, only when it is the one of its finished state; the state
  cached with the revision and the seed it was built from, never served for another, never written over a later
  revision, and brought forward by the moves made since; each table read at most once per request, the states kept
  while the header says nothing changed and let go by `reset()`; every change published with identifiers and counters
  only; stores in memory, reset between two requests of a worker, a cache that keeps nothing and a
  publisher that publishes nothing.
- `Scene\RoleplayingScene`: the phases of a role-playing scene — exploration, encounter by initiative, aftermath —,
  led by the first seat unless the table turns the option off, with actions, notes, encounters counted and an ending
  that reports them as statistics.
- Exceptions under one `GameEngineException` interface: `InvalidGame`, `IllegalMove`, `GameOver`,
  `GameInProgress`, `ConcurrentMove` and `UnknownTable`.
- Fuzz targets of the logs, of the states and of the snapshots read back, and benchmarks of a move, of replays of 100
  and 1 000 moves and of reading a log back.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
