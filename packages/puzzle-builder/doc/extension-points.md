# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Contract\AnswerCheck` | How the guesses of a puzzle are checked | `Answer\AnswerKey` |
| `Contract\GuessLimiter` | How many guesses a team makes on a puzzle in a period | `Limiter\RateLimiterGuessLimiter`, `Limiter\InMemoryGuessLimiter` |
| `Psr\EventDispatcher\EventDispatcherInterface` | Where the events go | none: nothing is dispatched |
| `Psr\Clock\ClockInterface` | The time of each move | `Symfony\Component\Clock\NativeClock` |

Every point is passed to a constructor; `PuzzleEngine` keeps nothing between two calls.

## Checking answers another way

A `Contract\AnswerCheck` reads a guess and gives an `Answer\Verdict`: `Verdict::correct()`, `Verdict::partial($message)`
or `Verdict::wrong()`. An accusation that names a suspect, a weapon and a room, read from a form as
`"butler|candlestick|library"`, is one check; a code whose digits may come in any order is another. The check:

- keeps its answer to itself, and is never asked for it;
- throws `Exception\InvalidAnswer` for a guess it does not read, which changes nothing in the progress;
- compares in constant time when it compares texts, with `hash_equals()`.

## Counting guesses another way

A `Contract\GuessLimiter` counts one attempt per call under a key and answers with a `Limiter\Allowance`:
`Allowance::granted()`, or `Allowance::refused($retryAfter)`. Its counts live where every worker reads them. A limiter
that also bounds a team across every puzzle builds its own key from the one it is given, which is
`scenario|team|puzzle` (`PuzzleEngine::key()`).

## The events

Each event is dispatched once its move is made, and names identifiers only — never a guess nor an answer:

| Event | Carries |
|---|---|
| `Event\PuzzleSolved` | The scenario, the team, the puzzle, the time, and whether the host skipped it |
| `Event\PartialAnswerGiven` | The scenario, the team, the puzzle and the time |
| `Event\WrongAnswerGiven` | The scenario, the team, the puzzle and the time |
| `Event\GuessThrottled` | The scenario, the team, the puzzle and when the team may guess again |
| `Event\HintRevealed` | The scenario, the team, the puzzle, the time, the tier, and whether the host pushed it |
| `Event\PuzzleOpened` | The scenario, the team, the puzzle, the time, and whether the host opened it |
| `Event\ScenarioCompleted` | The scenario, the team and the time, after the `PuzzleSolved` that ends it |

A bundle that pushes the moves to the screens of a team through Mercure or Messenger sends these identifiers; the
screen reads the view again from the server.

## Games built on the engine

The engine holds what every puzzle game shares, and a game adds its own rules on top, through the public API only:

- An escape room chains its puzzles with `Unlock::after()`, puts a countdown around the moves, and adds the hints'
  penalties to its final time.
- A murder mystery opens its clues act after act with `Unlock::manual()` and `PuzzleEngine::open()`, asks for the
  accusation as a puzzle whose `AnswerCheck` reads the suspect, the weapon and the motive, and ends with that puzzle as
  the finale; its other puzzles stay optional, since a finale ends the scenario whatever else is solved.

Both keep their own state beside the `Progress` and their own events beside those of the engine.
