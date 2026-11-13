# Reference

| Type | Role |
|---|---|
| `Scenario` | The puzzles and the finale of a scenario, its graph checked; read from plain values or JSON |
| `Puzzle` | A puzzle: identifier, title, check of answers, hints, unlock |
| `Unlock` | When a puzzle opens: puzzles required, how many of them, solves of the scenario, or by the host |
| `Hint` | A tier of hints: its text and penalty in seconds |
| `PuzzleEngine` | Guesses, hints, puzzles opened or skipped by the host, statuses and the view |
| `Progress` | Where a team stands: solves, puzzles opened, hints, guesses; written and read back as plain values |
| `PuzzleStatus` | `Locked`, `Open` or `Solved` |
| `GuessOutcome` | `Correct`, `Partial`, `Wrong` or `Throttled` |
| `GuessResult` | What a guess or a skip gave, and the progress after it |
| `HintResult` | The hint revealed, its tier, the tiers left, and the progress after it |
| `Answer\AnswerKey` | Accepted and partial answers, normalized, compared in constant time |
| `Answer\AnswerNormalizer` | How answers and guesses are normalized: case, accents, punctuation, spaces |
| `Answer\Verdict` | What a check says of a guess: correct, partial with its message, or wrong |
| `Record\Solved` | A puzzle solved, at a time, perhaps skipped |
| `Record\HintTaken` | A tier of hints revealed, at a time, asked or pushed |
| `View\ScenarioView` | What the players see of their game |
| `View\PuzzleView` | What the players see of one puzzle |
| `Limiter\Allowance` | Whether a guess is checked now, and when it will be |
| `Limiter\RateLimiterGuessLimiter` | Guesses counted by a limiter of `symfony/rate-limiter` |
| `Limiter\InMemoryGuessLimiter` | Guesses counted in a fixed window, in memory |
| `Contract\AnswerCheck` | How the guesses of a puzzle are checked |
| `Contract\GuessLimiter` | How many guesses a team makes on a puzzle in a period |
| `Event\PuzzleSolved` | A puzzle solved or skipped |
| `Event\PartialAnswerGiven` | A partial answer |
| `Event\WrongAnswerGiven` | A wrong answer |
| `Event\GuessThrottled` | A guess past the limit |
| `Event\HintRevealed` | A hint revealed |
| `Event\PuzzleOpened` | A puzzle opened by a solve or by the host |
| `Event\ScenarioCompleted` | A scenario ended |

`Instant` is internal: the one way the library writes a time, in UTC.

## Exceptions

| Exception | Thrown when |
|---|---|
| `Exception\PuzzleBuilderException` | The interface of every exception of the library |
| `Exception\InvalidScenario` | A scenario, a puzzle, an answer key, a hint, an unlock or a limiter the library refuses |
| `Exception\InvalidAnswer` | A guess not UTF-8, empty once normalized, or longer than 200 characters |
| `Exception\InvalidProgress` | A stored progress that does not read back against its scenario |
| `Exception\PuzzleRefused` | A move the game does not allow: a puzzle unknown, locked, solved, open already or without a hint left; a team named by an email address |

Nothing changes when an exception is thrown. Each message says what was expected, as
`The puzzle "vault" is still locked.`
