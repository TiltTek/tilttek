# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `TableSafety` | The service: settings, answers, agreement, pact, signals, check-ins, stars and wishes, export and erasure. |
| `TableSettings` | What a table chose: tools, named signals, who sees the signals, added themes, session zero. |
| `TableThemes` | The themes a table answers about: a catalogue's and its own. |
| `SafetyTool` | The tools, with their credits and their signals. |
| `Boundary` | A line or a veil. |
| `LinesAndVeils` | A participant's boundaries by theme. |
| `ConsentLevel` | Green, yellow or red. |
| `ConsentChecklist` | A participant's levels by theme. |
| `Participation` | A participant's answers at one table. |
| `TableAgreement` | The strictest answer of each theme, anonymous, and its warning preferences. |
| `SessionZero\QuestionKind` | Text, one option, several options. |
| `SessionZero\Question` | A question, translated or written by the game master. |
| `SessionZero\Template` | The questions of a session zero. |
| `SessionZero\Templates` | The built-in templates. |
| `SessionZero\Answers` | A participant's answers to a session zero. |
| `SessionZero\Pact` | The answers of a table, gathered without their authors. |
| `Signal\SignalKind` | The signals of play. |
| `Signal\Audience` | The table, or the game master alone. |
| `Signal\Signal` | A signal raised at a table. |
| `Signal\CheckInTally` | The answers to the last check-in, counted. |
| `Feedback\StarsAndWishes` | A participant's stars and wishes. |
| `Feedback\Feedback` | The stars and wishes of one participant for one session. |
| `Feedback\FeedbackSummary` | A session's stars and wishes, without their authors. |
| `Contract\TableStore` | Where the settings are kept. |
| `Contract\ParticipationStore` | Where the answers are kept. |
| `Contract\SignalStore` | Where the signals are kept. |
| `Contract\FeedbackStore` | Where the stars and wishes are kept. |
| `Contract\SignalObserver` | Told of each signal raised or seen. |
| `Store\InMemoryTableStore`, `InMemoryParticipationStore`, `InMemorySignalStore`, `InMemoryFeedbackStore` | The stores in memory. |
| `Exception\TableSafetyException` | Every exception of the library. |
| `Exception\InvalidAnswer` | A value the library refuses. |
| `Exception\ToolNotEnabled` | A tool the table does not use. |
| `Exception\NotGameMaster` | A check-in asked by someone else than the game master. |
