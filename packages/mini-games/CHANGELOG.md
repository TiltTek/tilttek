# Changelog

All notable changes to `tilttek/mini-games` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Contract\MiniGame`: a game of `tilttek/game-engine` played alone, which declares its scoring, its controls and how a
  result is shared.
- `ScoreOrder` and `Scoring`: higher or lower is better, declared by each game, with a strict comparison, records set
  by better scores only and the best of some scores.
- `Control`: the actions of a game and the keys that perform them, so that every game is played with the keyboard
  alone.
- `Day`, `DailySeed` and `DailyGames`: the daily game, the same for every player of a day from an HMAC of the game and
  the day under a key derived from the site's secret by HKDF, at a table of each player's own; the score sent once to
  the score sink when the game ends; the log of a finished table, seed included, once its day is over.
- `ShareGrid`, `Cell` and `CellSymbols`: a result shared as text, in coloured squares or plain characters, that tells
  how each step went and never what it was.
- `Ending` and `Tone`: a victory celebrated, any other ending told kindly; a record set by a better score only.
- `DailyScore`, `DailyHistory`, `Contract\ScoreSink`, `Store\NullScoreSink` and `Store\InMemoryScoreSink`: the scores
  of the daily games sent to a port, and a player's history — days played, wins, best score, spread of scores — that
  counts no streak.
- `MiniGames`: the mini-games of an application, by name, and the same games for the engine's `Tables`.
- `Game\BullsAndCows` and `Game\Code`, `Game\HigherOrLower`: two traditional games written as models, one where fewer
  guesses are better, one where more right calls are better.
- Exceptions under one `MiniGameException` interface: `InvalidMiniGame` and `SeedNotRevealable`.
- A fuzz target of the guesses of Bulls and Cows and a benchmark of a whole daily game.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
