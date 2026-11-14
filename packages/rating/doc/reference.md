# Reference

| Type | Role |
|---|---|
| `Ratings` | Records each result once and delivers its events again after a failed listener, gives ratings, says which are provisional, gives standings with or without them, publishes and hides, replays, exports and forgets |
| `GameResult` | One game played: its sides and their ranks; written and read back as plain values |
| `MemberRating` | A member's rating of a game, as the store keeps it, at a version |
| `RatingChange` | How a recorded result moved one member's score |
| `Visibility` | `Private` or `Public` |
| `Contract\Rating` | A rating in whole numbers, by name |
| `Contract\RatingSystem` | Rates the members of a result, gives the score, says whether a rating is uncertain and reads a rating back |
| `Contract\RatingStore` | Where the ratings are kept, with the results each game counts |
| `Contract\PlaySource` | Where the results of a game are read from, for a replay |
| `Elo\EloSystem` | The Elo rating |
| `Elo\EloRating` | An Elo rating in hundredths |
| `Elo\KFactor` | A fixed or decreasing K |
| `Elo\KFactorStep` | One step of a decreasing K |
| `OpenSkill\OpenSkillSystem` | The Bayesian approximation of Weng and Lin |
| `OpenSkill\OpenSkillModel` | `PlackettLuce` or `ThurstoneMosteller` |
| `OpenSkill\SkillRating` | A skill, its mu and its sigma in millionths |
| `Event\RatingChanged` | Dispatched for each member of a recorded result |
| `Event\PeakReached` | Dispatched for a member above their highest score |
| `Store\InMemoryRatingStore` | The store in memory, for tests and scripts |
| `Exception\RatingException` | The interface of every exception of the library |
| `Exception\InvalidRating`, `RatingRefused`, `ResultAlreadyRecorded`, `RatingConflict` | See [Exceptions](exceptions.md) |

`Identifier` and `OpenSkill\Gaussian` are internal.
