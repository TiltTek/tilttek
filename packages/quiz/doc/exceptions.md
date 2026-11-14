# Exceptions

Every exception of the library implements `Exception\QuizException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidQuiz` | `InvalidArgumentException` | A value is out of its bounds: an identifier, a prompt, points, a time limit, choices, an answer of the wrong kind, a pseudonym, a PIN, a speed bonus, the teams of a session, a word list or a topic prefix |
| `QuizRefused` | `DomainException` | The rules refuse a move now: an answer after the time or given twice, a guest at their own pace, another member's play, results before the end of a play, a replay refused, a session in the wrong state, a team the session does not have, an unknown quiz, play or session, no free PIN |
| `QuizConflict` | `RuntimeException` | A play or a session changed in the store since it was read, its identifier is taken, or another play of the member took its attempt |

The messages name identifiers and bounds, never a pseudonym or an answer typed.
