# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `SheetDefinition` | The sheet of a game: categories, tie-breakers and teams, read from JSON and written back |
| `Category` | One line of a sheet: entered between its bounds, or computed |
| `Tiebreaker`, `Direction` | A tie-breaker, and which value wins |
| `TeamCombine` | How members' totals make a team's score: sum or best |
| `Comparison` | The condition of a bonus |
| `Operation\Operation` | How a computed category gets its value |
| `Operation\Sum`, `Product`, `Majority`, `Bonus` | The operations |
| `Operation\Operations` | Reads back the operations written to arrays |
| `ParticipantId`, `TeamId`, `MatchId` | Opaque identifiers |
| `Team` | A team and its members |
| `Entries` | Participants, teams and the values entered |
| `Scoring\Scorer` | Computes a scoreboard from a sheet and its entries |
| `Scoring\Scoreboard`, `ParticipantScore`, `TeamScore`, `Step` | The scores, ranks and explanations |
| `SheetStatus`, `MatchSheet` | The status and the stored sheet of a match |
| `ScoreKeeper` | Prepares, publishes, fills, closes and reopens the sheets of a store |
| `MatchResult`, `Placement` | The neutral result of a closed match |
| `Message\MatchResultRecorded` | The message of a recorded result, with identifiers only |
| `BlankSheet` | The blank sheet to print, as neutral data |
| `Contract\MatchSheetStore` | The store port |
| `Store\InMemoryMatchSheetStore` | The store in memory, for tests |
| `Exception\ScoreSheetsException` and its five exceptions | [Exceptions](exceptions.md) |
