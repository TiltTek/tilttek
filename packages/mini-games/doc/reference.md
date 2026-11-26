# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Contract\MiniGame` | A game of the engine played alone, with its scoring, its controls and its share |
| `Scoring` | Which way a game's scores go, and the seat statistic that holds the score |
| `ScoreOrder` | Higher or lower is better: strict comparison, records, the best of some scores |
| `Control` | An action of a game and the keys that perform it |
| `Day` | A calendar day of the site, read, compared and numbered |
| `DailySeed` | The seed of a game on a day, from the site's secret |
| `DailyGames` | The players' tables of the daily games: start, play, view, share, ending, and the log once the day is over |
| `DailyScore` | The result of a player's daily game, sent to the score sink |
| `DailyHistory` | A player's daily scores of one game added up, without streaks |
| `Contract\ScoreSink` | The port the scores go to |
| `Store\NullScoreSink`, `Store\InMemoryScoreSink` | A sink that keeps nothing, and one in memory for tests |
| `Ending`, `Tone` | How a game ended for its player: a victory celebrated, or a game completed told kindly; a record or not |
| `ShareGrid`, `Cell`, `CellSymbols` | A result shared as text, its cells and the symbols they are written with |
| `MiniGames` | The mini-games of an application, by name, and the same games for the engine |
| `Game\BullsAndCows`, `Game\Code` | The guessing game of a code of different digits, and its codes |
| `Game\HigherOrLower` | The game of ten calls, higher or lower |
| `Exception\MiniGameException`, `InvalidMiniGame`, `SeedNotRevealable` | [Exceptions](exceptions.md) |
