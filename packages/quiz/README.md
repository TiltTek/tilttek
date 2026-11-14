# tilttek/quiz

**Quiz games: questions of six kinds, a timer on an injected clock, scoring with a speed bonus, self-paced plays for
members and live sessions with pseudonymous guests and teams, standings, a pseudonym filter, live updates through
Mercure, and the events and Stimulus hook of a celebration.**

A quiz is a list of questions, each worth points and maybe timed. A member plays it at their own pace: each question
comes when the previous one is answered, and the run ends after the last; the play is the member's alone, its results
show once it is over, and only the member's first play of a quiz counts, the later ones being practice. A host plays it live: players join a session
by its six-digit PIN, members or guests under a pseudonym, alone or in teams, and the host shows each question to all
at once, closes it, shows the standings and moves on, every screen following through Mercure. A guest has no account;
the pseudonym passes a word list first, and once the session is over, the application offers the guest to become a
member, and their place and score follow them if they do.

Every answer is graded on the server, in thousandths so that partial credit stays exact, and scored by the quiz's
rule: accuracy by default, or speed, where a right answer earns a bonus that falls to nothing at the end of the time.
The time is read from the PSR-20 clock the application gives, never from the browser. The end of a run dispatches
`QuizCompleted`, then `PerfectScore` for a perfect one that counts and `TeamWon` for the winning teams; the result template and its
Stimulus controller turn them into the DOM events `quiz:completed`, `quiz:perfect` and `quiz:team-won`, which the
celebration of the PHIL UX kit plays on.

It is for an application that runs trivia nights, quizzes on a game's lore or rules, or a quick round between talks.
What is asked without a right answer — a poll, a vote — belongs to a poll library; a question bank kept elsewhere plugs
in through `Contract\QuestionSource`. It is a plain PHP library whose only dependencies are `psr/clock` and
`psr/event-dispatcher`, with `ext-intl` and `ext-mbstring` to compare typed answers; `symfony/mercure` is needed only
for the live updates.

## Status

| Part | State |
|---|---|
| `Quiz`, `Scoring`, `ScoringMode`, `Grade`, `QuestionTimer`, `AnswerResult` | Implemented and tested |
| Questions: `SingleChoiceQuestion` (true-or-false included), `MultipleChoiceQuestion`, `OrderingQuestion`, `TypedAnswerQuestion`, `NumericQuestion` | Implemented and tested |
| Answers: `ChoiceAnswer`, `OrderAnswer`, `TextAnswer`, `NumberAnswer` | Implemented and tested |
| `SelfPacedQuizzes` and `SelfPaced\Play`: members at their own pace, plays bound to their member, results after the end, practice replays, erasure | Implemented and tested |
| `SelfPaced\Replays`, `SelfPaced\Feedback`, `SelfPaced\PerfectScoreRule` | Implemented and tested |
| `LiveQuizzes`, `Live\LiveSession`, `Live\LiveState`: live sessions, PIN, guests and their claim by a member | Implemented and tested |
| `Pin`: the PIN a player types | Implemented and tested |
| `Player`, `Leaderboard`, `Standing` | Implemented and tested |
| Teams: `Team\TeamLeaderboard`, `Team\TeamStanding`, `Team\TeamScoring` | Implemented and tested |
| `Pseudonym\WordListFilter` with the library's short list, `Pseudonym\ChainFilter` | Implemented and tested |
| `Mercure\LiveSessionPublisher`: the moves of a live session pushed through Mercure | Implemented and tested |
| Events: `QuizCompleted`, `PerfectScore`, `LiveQuizFinished`, `TeamWon`, `GuestInvited`, `PlayerJoined`, `QuestionShown`, `QuestionClosed` | Implemented and tested |
| `View\ResultView`, `templates/result.html.twig` and the Stimulus controller `result` | Implemented and tested |
| Stores in memory and their contracts, `Contract\QuestionSource`, `Contract\PseudonymFilter` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Questions and scoring](doc/questions-and-scoring.md) | The kinds of questions, grades, the timer, accuracy and speed. |
| [Playing](doc/playing.md) | Plays at one's own pace, live sessions, guests and their pseudonyms, teams and standings. |
| [Live updates](doc/live-updates.md) | The moves of a live session pushed through Mercure. |
| [Celebrations](doc/celebrations.md) | The events, the result template and its Stimulus controller. |
| [Storing](doc/storing.md) | The store contracts, versions and concurrent answers. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `QuizException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Quiz\Answer\{ChoiceAnswer, NumberAnswer};
use Tilttek\Quiz\Question\{Choice, NumericQuestion, SingleChoiceQuestion};
use Tilttek\Quiz\Store\{InMemoryPlayStore, InMemoryQuizCatalog};
use Tilttek\Quiz\{Player, Quiz, Scoring, SelfPacedQuizzes};

$quiz = new Quiz('lore', [
    new SingleChoiceQuestion('d20', 'How many faces has a d20?', [new Choice('a', 'Twelve'), new Choice('b', 'Twenty')], 'b'),
    new NumericQuestion('year', 'In which year was the first role-playing game published?', 1974, tolerance: 1),
], Scoring::speed(), timeLimit: 20);

$quizzes = new SelfPacedQuizzes(new InMemoryQuizCatalog($quiz), new InMemoryPlayStore(), $dispatcher, $clock);
$quizzes->start('play-7', 'lore', Player::member('member:44'));
$quizzes->answer('play-7', 'member:44', ChoiceAnswer::of('b'));     // null: nothing shows before the end
$quizzes->answer('play-7', 'member:44', new NumberAnswer(1974));    // the last: its AnswerResult, QuizCompleted, then PerfectScore
$quizzes->results('play-7', 'member:44');                           // every AnswerResult: grade, points, bonus
```

## Requirements

PHP 8.5 or later with `ext-intl` and `ext-mbstring`; `psr/clock` and `psr/event-dispatcher`. The template needs Twig,
and its controller Stimulus, only where the application uses them; the live updates need `symfony/mercure`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
