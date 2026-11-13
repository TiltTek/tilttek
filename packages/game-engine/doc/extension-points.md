# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\Game` | A game: its definition, setup, rules, phases entered, ending and views | [Writing a game](writing-a-game.md) |
| `Contract\LogStore` | The storage of the logs, the truth of every table | [Storing and publishing](storing-and-publishing.md#the-log-store) |
| `Contract\StateCache` | A cache of the current states | [Storing and publishing](storing-and-publishing.md#the-state-cache) |
| `Contract\TablePublisher` | How the players learn that a table changed: Mercure, a WebSocket | [Storing and publishing](storing-and-publishing.md#publishing) |

Every point is passed to the constructor of `Tables` or of `Games`; the engine and `Tables` keep no state of their own
between two calls.
