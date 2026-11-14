# Changelog

All notable changes to `tilttek/quiz` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-14

### Added

- `Quiz`: 1 to 200 questions with distinct identifiers, a default time limit of 5 to 600 seconds, its scoring, the
  grading and scoring of an answer against its timer, `maxScore()`, and `Quiz::fromSource()` composing a quiz from a
  bank of a `Contract\QuestionSource`.
- Questions, behind `Contract\Question`: `SingleChoiceQuestion` with `trueOrFalse()`, `MultipleChoiceQuestion` with
  optional partial credit, `OrderingQuestion` with optional partial credit, `TypedAnswerQuestion` comparing folded text
  — case, spaces and, unless kept, accents — and `NumericQuestion` with a tolerance; 2 to 12 choices, prompts of 1 to
  1 000 bytes, 0 to 10 000 points.
- Answers, behind `Contract\Answer`: `ChoiceAnswer`, `OrderAnswer`, `TextAnswer` of at most 200 bytes of UTF-8 and
  `NumberAnswer`; a question refuses an answer of another kind.
- `Grade` in thousandths, `Scoring` and `ScoringMode`: accuracy by default, or speed with a bonus of 0 to 100 percent
  of the points falling in a straight line to nothing at the end of the time.
- `QuestionTimer`: milliseconds read from the PSR-20 clock of the application; `AnswerResult`, timed out included.
- `SelfPacedQuizzes` and `SelfPaced\Play`: a member's run at their own pace, each question shown when the previous one
  is answered; a guest refused; each play bound to its member, who alone answers, reads and sees its result; the
  results held back until the play is over unless `SelfPaced\Feedback::AfterEachAnswer`; each play at an attempt, only
  the first counting, later ones practice or refused by `SelfPaced\Replays`, two plays started at once never both
  first; `PerfectScore` once per quiz and member by default, or for every perfect play by
  `SelfPaced\PerfectScoreRule`; `erasePlayer()` deleting a member's plays.
- `LiveQuizzes`, `Live\LiveSession` and `Live\LiveState`: a six-digit PIN drawn from a secure source and free among
  the sessions not over, up to 1 000 players, members and guests, each question shown, answered once per player in
  time, closed with the absent timed out, then reviewed; the changes made again on a conflict, up to eight times.
- `Pin`: the PIN a player types, spaces and hyphens left out, at most 16 bytes read, refused with `InvalidQuiz` unless
  six ASCII digits, read by `LiveQuizzes::join()` before any store is asked.
- Teams: 2 to 50 per session, by identifier; a player joins the team given or the smallest; `Team\TeamLeaderboard`
  and `Team\TeamStanding` rank them by `Team\TeamScoring` — the total of their players, or their mean rounded half up
  — then by time, with shared ranks; a guest's team follows the member they became.
- `Player`: a member by opaque reference, or a guest under a pseudonym of 1 to 24 characters without control or
  invisible characters, unique per session whatever its case, filtered by an optional `Contract\PseudonymFilter`;
  `LiveQuizzes::claimGuest()` gives a guest's place and answers to the member they became, and the session forgets the
  pseudonym.
- `Pseudonym\WordListFilter`, its file opening on a content warning: whole words and terms anywhere, folded — case,
  accents, digit and symbol stand-ins —, up to 10 000 words, with `defaults()`, the library's short list in English and
  in French, and `with()` for the application's words; `Pseudonym\ChainFilter` asks several filters in order.
- `Leaderboard` and `Standing`: the highest score first, then the fewer milliseconds of the scoring answers, shared
  ranks (1, 1, 3), `top()` with ties at the edge.
- Events carrying identifiers only: `QuizCompleted` and `PerfectScore`, both saying whether a play is practice,
  `LiveQuizFinished`, `TeamWon`, `GuestInvited`, and the moves of a live session, `PlayerJoined`, `QuestionShown` and
  `QuestionClosed`, each dispatched once the change is saved.
- `Mercure\LiveSessionPublisher`: the moves of a live session pushed as private Mercure updates on the session's
  topic, naming the session and the position of a question only; `symfony/mercure` suggested.
- `View\ResultView` and `templates/result.html.twig`: the score out of the most, the rank of a live run and of the
  player's team, whether a play is practice, the blocks `summary` and `celebration`; the Stimulus controller `result`
  dispatching `quiz:completed`, `quiz:perfect` and `quiz:team-won` on the window once per run in a browser tab, for the
  celebration of the PHIL UX kit.
- `Contract\QuizCatalog`, `Contract\PlayStore` — with the count of a member's plays of a quiz, one play per attempt
  and the erasure of a member — and `Contract\LiveSessionStore`, with versions, and their stores in memory, with
  `Store\InMemoryQuestionSource`.
- Exceptions under `Exception\QuizException`: `InvalidQuiz`, `QuizRefused` and `QuizConflict`.
- Benchmarks of an answer in a session of 1 000 players, of its standings, of the standings of its players in ten
  teams and of a pseudonym through the word list.
- Fuzz targets of the PIN and of a pseudonym through the word list.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
