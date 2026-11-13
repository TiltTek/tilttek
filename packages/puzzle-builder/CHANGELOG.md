# Changelog

All notable changes to `tilttek/puzzle-builder` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Scenario`, `Puzzle`, `Unlock` and `Hint`: 1 to 200 puzzles of an identifier and a title, a check of answers, up to
  10 tiers of hints with a penalty in seconds, and an unlock by the puzzles required, how many of them, solves of the
  scenario, or by the host; a finale, or every puzzle, ends the scenario; the graph checked when the scenario is made —
  every requirement held, no cycle, named with its path, and every puzzle reachable —, with an order a game can solve
  it in; read from plain values or JSON within 256 KiB and 8 levels, a field not known refused.
- `Answer\AnswerKey`, `Answer\AnswerNormalizer` and `Answer\Verdict`: 1 to 10 accepted answers and up to 20 partial
  answers with their message, normalized on the server — compatibility form, case folding, invisible characters,
  accents, punctuation and symbols, spaces, the last three each kept on demand —, compared in constant time.
- `PuzzleEngine`: guesses of open puzzles checked on the server and counted under the scenario, the team and the
  puzzle, a guess past the limit not checked and given its time to retry; hints revealed tier after tier, asked or
  pushed; puzzles the host opens or skips; the status of each puzzle; the puzzles a solve opens and whether it ends the
  scenario; a team named by an email address refused.
- `Progress`, `Record\Solved` and `Record\HintTaken`: the state of a game kept by the application, written as plain
  values with every time in UTC to the microsecond, and read back checked against the scenario.
- `View\ScenarioView` and `View\PuzzleView`: what the players see, in the order of the scenario, without answers nor
  the title and hints of a locked puzzle.
- `Contract\AnswerCheck` and `Contract\GuessLimiter`, with `Limiter\RateLimiterGuessLimiter` on `symfony/rate-limiter`,
  suggested, `Limiter\InMemoryGuessLimiter` in a fixed window, and `Limiter\Allowance`.
- `Event\PuzzleSolved`, `Event\PartialAnswerGiven`, `Event\WrongAnswerGiven`, `Event\GuessThrottled`,
  `Event\HintRevealed`, `Event\PuzzleOpened` and `Event\ScenarioCompleted`, dispatched through PSR-14 and naming
  identifiers only.
- `Exception\PuzzleBuilderException`, with `InvalidScenario`, `InvalidAnswer`, `InvalidProgress` and `PuzzleRefused`.
- A fuzz target for scenarios, and benchmarks of a scenario of 200 puzzles read, guessed and viewed.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
