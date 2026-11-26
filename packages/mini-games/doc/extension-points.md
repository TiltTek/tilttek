# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\MiniGame` | Its own mini-games | [Writing a mini-game](writing-a-mini-game.md) |
| `Contract\ScoreSink` | Where the scores of the daily games go: its leaderboards, its activity journal | [Daily games](daily-games.md#the-score-sink) |
| `DailySeed` | The secret of its daily games, from its configuration | [Daily games](daily-games.md#the-seed-of-the-day) |
| `CellSymbols` | The symbols of its shared results | [Sharing a result](sharing.md#writing-a-grid) |
| The stores of `tilttek/game-engine` | The log store, the state cache and the publisher of `Tables` | [Installation](installation.md#wiring-the-library) |
| The celebration | What a victory plays, in its interface | [Endings, records and history](endings-and-history.md#the-tone-of-an-ending) |

`DailyGames`, `MiniGames` and the example games keep no state between two calls.
