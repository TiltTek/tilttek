# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\Question` | A kind of question of its own, graded on the answer alone | [Questions and scoring](questions-and-scoring.md#questions) |
| `Contract\Answer` | The answer its own kind of question takes | [Questions and scoring](questions-and-scoring.md#questions) |
| `Contract\QuestionSource` | A bank of questions: an assessment library, a file, an editor | [Questions and scoring](questions-and-scoring.md#composing-a-quiz-from-a-bank) |
| `Contract\QuizCatalog` | Where the quizzes are read | [Storing](storing.md) |
| `Contract\PlayStore`, `Contract\LiveSessionStore` | Where the plays and the sessions are kept, at a version | [Storing](storing.md) |
| `Contract\PseudonymFilter` | The pseudonyms a guest may not take: the library's word list, its own list, a moderation service | [Playing](playing.md#filtering-pseudonyms) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of the end of each run | [Celebrations](celebrations.md#the-events) |
| `Psr\Clock\ClockInterface` | The time every timer reads | [Questions and scoring](questions-and-scoring.md#the-timer) |
| `Random\Randomizer` | The source of the PINs | [Installation](installation.md#wiring-the-library) |
| `Symfony\Component\Mercure\HubInterface` and a topic prefix | Where the moves of a live session are pushed | [Live updates](live-updates.md) |
| The blocks `summary` and `celebration` | The words of the result, and its celebration | [Celebrations](celebrations.md#the-result-template) |
| Team identifiers and `Team\TeamScoring` | The teams of a session, and how their players' scores add up | [Playing](playing.md#teams) |

Every point is passed to a constructor; none of the library's services keeps state between two calls.
