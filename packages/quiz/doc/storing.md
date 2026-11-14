# Storing

The library gives three stores in memory, for tests and scripts; an application keeps its quizzes, plays and sessions
in its own storage by implementing the contracts.

| Contract | What it keeps |
|---|---|
| `Contract\QuizCatalog` | The quizzes, by identifier; read only |
| `Contract\PlayStore` | The plays at one's own pace, counted by quiz and member, and erased by member |
| `Contract\LiveSessionStore` | The live sessions, found by identifier or by the PIN of a session that is not over |

## Versions

A play and a live session count their changes: each change gives them their next version, starting at 1. `add()`
keeps a new one at version 1, and refuses an identifier taken with `QuizConflict`; `save()` keeps the next version, and
refuses with `QuizConflict` when the version stored is not the one before it. Two answers sent at once from the same
version never both count: the second is refused.

`LiveQuizzes` reads the session again and makes the change again on a conflict, up to `LiveQuizzes::ATTEMPTS` times,
so that the answers of many players are all kept; `SelfPacedQuizzes` lets the conflict of an answer through, since a
member answers one question at a time. A store backed by a database writes `save()` as an update conditioned on the
version.

## Attempts

A play carries its `attempt` at the quiz, and `PlayStore::countOf($quiz, $player)` tells how many plays of the quiz a
member started. `add()` refuses with `QuizConflict` a play whose member has a play of the same quiz at the same
attempt: `SelfPacedQuizzes::start()` then counts again and takes the next attempt, up to `SelfPacedQuizzes::ATTEMPTS`
times, so that two plays started at once never both count as the first. A store backed by a database holds this with a
unique index on the quiz, the player and the attempt. `erasePlayer($player)` deletes every play of a member and
returns how many there were.

## What a store keeps

A play holds its quiz's identifier, its player, its attempt, the moment each question was shown and the result of each
answer. A
session holds its PIN, its state, its teams and their scoring, its players — a guest's pseudonym among them — with the
team of each, and the result of each answer. A
store that keeps a guest's pseudonym keeps it with the session and deletes it with the session; no event carries it.
