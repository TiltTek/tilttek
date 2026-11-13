# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Tilttek\GameEngine\Contract\Game` | A game: any service implementing it, tagged `tilttek_game_engine.game` by autoconfiguration | [Installation](installation.md#the-games) |
| `Application\Contract\ViewerResolver` | Who reads a table through the endpoints: a seat and its preferences, the audience, or no one; `view.resolver` | [Live tables](live-tables.md#the-viewer) |
| `Application\Contract\TableLock` | Another lock of the tables; the alias of the bundle's when `lock.factory` is set | [Storage](storage.md#the-lock) |
| `Tilttek\GameEngine\Contract\LogStore` | Another store of the logs, by aliasing the interface | [Storage](storage.md#the-logs) |
| `Tilttek\GameEngine\Contract\StateCache` | Another state cache, by aliasing the interface | [Storage](storage.md#the-state-cache) |
| `Tilttek\GameEngine\Contract\TablePublisher` | Another way to tell the players, by aliasing the interface | [Live tables](live-tables.md#publishing) |
| The `game-table:view` event | The rendering of a game's view in the page | [Live tables](live-tables.md#the-template-and-the-stimulus-controller) |

Every service of the bundle keeps no state between two requests; the store in memory, for tests, is reset between two
requests of a worker.
