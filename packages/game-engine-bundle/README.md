# tilttek/game-engine-bundle

**Online tables for Symfony on tilttek/game-engine: logs on Doctrine DBAL, states in a shared cache, a lock per table,
every change pushed by Mercure, and the view of each seat read from the server.**

tilttek/game-engine-bundle runs the tables of `tilttek/game-engine` in a Symfony application. Every service that
implements the library's `Game` contract is a game. The log of each table — the truth of the table, with its seed —
is kept by Doctrine DBAL on MySQL, PostgreSQL or SQLite, appended in one statement that refuses a concurrent move; the
current state is cached in a PSR-6 pool shared by every process, as JSON text rather than a PHP object; each move is
made under the lock of its table when a lock factory is named. A read costs the row of the table and the moves made
since its cached state, read window by window and at most once per request; a state built before a move was taken back
is never served.

The server checks every move and Mercure pushes: each change is published privately on the topic of its table with
identifiers and counters only, and each player's page reads the view of its seat from an endpoint that asks the
application who the reader is. The log of a table, seed included, is given only once its game is over, for anyone to
verify every draw against the commitment shown at the opening, and no move is taken back after that. Seats are opaque
identifiers of the table: one that looks like an email address or a reference to a member is refused. A template built
of the recipes of the PHIL UX kit and a Stimulus controller show the table and read the view again at each update.

The library alone suits an application without Symfony.

## Status

| Part | State |
|---|---|
| `TilttekGameEngineBundle` and its configuration `tilttek_game_engine` | Implemented and tested |
| The games gathered from the container, the library's role-playing scene on demand | Implemented and tested |
| `DbalLogStore` on MySQL, PostgreSQL and SQLite, its tables in the generated schema | Implemented and tested |
| `CacheStateCache`, the states in a shared PSR-6 pool as JSON text, written under the lock of their key | Implemented and tested |
| `SymfonyTableLock` and `GameTables`, the moves under the lock of their table | Implemented and tested |
| `SeatIdentifiers`, opaque seat identifiers only | Implemented and tested |
| `MercureTablePublisher`, every change on the topic of its table | Implemented and tested |
| `TableController`, the view of a seat and the log of a finished table, through the application's `ViewerResolver` | Implemented and tested |
| `tilttek:game-engine:setup` and `tilttek:game-engine:verify` | Implemented and tested |
| The template of a table, its Twig functions and the Stimulus controller `game-table` | Implemented and tested |
| The Flex recipe, in `recipe/` | Written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, the tables of the logs, the games, the routes, the controller and the kit. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Storage](doc/storage.md) | The logs on Doctrine DBAL, the state cache, one read per request, the lock, and opaque seat identifiers. |
| [Live tables](doc/live-tables.md) | Mercure, the viewer, the endpoints, the template and the Stimulus controller. |
| [Commands](doc/commands.md) | Creating the tables of the logs and verifying a log. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and testing a game. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```yaml
# config/packages/tilttek_game_engine.yaml
tilttek_game_engine:
    lock: { factory: lock.factory }
    mercure: { enabled: true }
    view: { resolver: App\Game\SeatOfTheMember }
```

```php
public function move(GameTables $tables, string $table, Request $request): Response
{
    $seat = $this->seating->seatOf($table, $this->getUser());
    $tables->play($table, new Move('chess.move', $seat, ['from' => 'e2', 'to' => 'e4']), $request->getPayload()->getInt('seen'));

    return new Response(null, Response::HTTP_NO_CONTENT);
}
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
