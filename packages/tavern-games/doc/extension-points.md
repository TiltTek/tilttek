# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\TavernGame` | Its own tavern games | [Writing a tavern game](writing-a-tavern-game.md) |
| `Contract\TurnTimer` | The clock of its turns, which calls back the timeouts | [Tables and turns](tables-and-turns.md#the-turn-timer) |
| `Contract\Simulation` | A position whose odds it estimates | [Odds](odds.md#the-estimator) |
| The stores of `tilttek/game-engine` | The log store, the state cache and the publisher of `Tables` | [Installation](installation.md#wiring-the-library) |
| The celebration | What a victory plays, in its interface | [Tables and turns](tables-and-turns.md#endings) |

`TavernTables`, `TavernGames`, `DiceCup` and the games keep no state between two calls.
