# Reference

| Type | Role |
|---|---|
| `Quiz` | The questions in order, the default time limit and the scoring; grades and scores an answer |
| `Scoring`, `ScoringMode` | Accuracy or speed, and the bonus of a quick answer |
| `Grade` | How right an answer is, in thousandths |
| `QuestionTimer` | When a question was shown and how long it lasts |
| `AnswerResult` | What an answer earned: grade, points, bonus, milliseconds, timed out or not |
| `Player` | A member, or a guest under a pseudonym |
| `Pin` | Reads the PIN a player types |
| `SelfPacedQuizzes` | Starts, answers and shows the plays at one's own pace, each to its member alone; erases a member's plays |
| `SelfPaced\Play` | A member's run at their own pace, at an attempt and a version |
| `SelfPaced\Replays` | `Practice` or `Refused`: a member's later plays of a quiz |
| `SelfPaced\Feedback` | `AfterPlay` or `AfterEachAnswer`: when a member sees their results |
| `SelfPaced\PerfectScoreRule` | `OncePerQuizAndMember` or `EveryPerfectPlay` |
| `LiveQuizzes` | Opens, joins, shows, answers, closes and finishes the live sessions, and delivers the events of their end again after a failed listener; gives a guest's place to a member |
| `Live\LiveSession` | A live session, at a version |
| `Live\LiveState` | `Lobby`, `Question`, `Review` or `Finished` |
| `Leaderboard`, `Standing` | The standings of a live session |
| `Team\TeamLeaderboard`, `Team\TeamStanding` | The standings of the teams of a live session |
| `Team\TeamScoring` | `Total` or `Average` |
| `Pseudonym\WordListFilter`, `Pseudonym\ChainFilter` | Filters of pseudonyms: a word list, and filters asked in order |
| `Mercure\LiveSessionPublisher` | Pushes the moves of a live session through Mercure |
| `Question\SingleChoiceQuestion`, `MultipleChoiceQuestion`, `OrderingQuestion`, `TypedAnswerQuestion`, `NumericQuestion` | The kinds of questions |
| `Question\Choice` | A choice, or an item to order |
| `Answer\ChoiceAnswer`, `OrderAnswer`, `TextAnswer`, `NumberAnswer` | The kinds of answers |
| `Contract\Question`, `Contract\Answer` | A question, an answer |
| `Contract\QuizCatalog`, `Contract\PlayStore`, `Contract\LiveSessionStore` | The stores |
| `Contract\QuestionSource` | A bank of questions |
| `Contract\PseudonymFilter` | The pseudonyms allowed |
| `Store\InMemoryQuizCatalog`, `InMemoryPlayStore`, `InMemoryLiveSessionStore`, `InMemoryQuestionSource` | The stores in memory |
| `Event\QuizCompleted`, `PerfectScore`, `LiveQuizFinished`, `TeamWon`, `GuestInvited` | See [Celebrations](celebrations.md#the-events) |
| `Event\PlayerJoined`, `QuestionShown`, `QuestionClosed` | See [Playing](playing.md#live) |
| `View\ResultView` | What the result template shows |
| `Exception\QuizException`, `InvalidQuiz`, `QuizRefused`, `QuizConflict` | See [Exceptions](exceptions.md) |

`Identifier` and `Question\Rules` are internal.

The template `templates/result.html.twig` and the Stimulus controller `result`, in `assets/`, are described in
[Celebrations](celebrations.md).
