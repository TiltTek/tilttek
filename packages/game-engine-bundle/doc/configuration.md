# Configuration

The bundle's configuration lives under `tilttek_game_engine`; every option has a default:

```yaml
# config/packages/tilttek_game_engine.yaml
tilttek_game_engine:
    store: doctrine
    connection: default
    cache:
        pool: cache.app
        lifetime: ~
    lock:
        factory: lock.factory
        ttl: 30
    mercure:
        enabled: true
        hub: Symfony\Component\Mercure\HubInterface
        topic: 'urn:tilttek-game-engine:table:{table}'
        private: true
    view:
        resolver: App\Game\SeatOfTheMember
        audience: false
    roleplaying_scene: false
    translation_domain: tilttek_game_engine
    kit_subdirectory: ~
```

| Option | Default | What it does |
|---|---|---|
| `store` | `doctrine` | Where the logs are kept: `doctrine` on the connection below, or `memory` for tests, reset between two requests of a worker. |
| `connection` | `default` | The Doctrine DBAL connection of the logs. |
| `cache.pool` | `cache.app` | The PSR-6 pool the states are cached in, shared by every process; `null` replays the log at every read. |
| `cache.lifetime` | `null` | The seconds a state is kept after it was cached; as long as the pool keeps it when `null`. |
| `lock.factory` | `null` | The service of a Symfony lock factory that keeps two moves at one table apart, and two writes of the state cache of one table; none by default. |
| `lock.ttl` | `30` | The seconds a lock is kept when the process that holds it dies, 1 or more. |
| `mercure.enabled` | `false` | Publishes every change on the Mercure topic of its table. |
| `mercure.hub` | `Symfony\Component\Mercure\HubInterface` | The service of the hub. |
| `mercure.topic` | `urn:tilttek-game-engine:table:{table}` | The topic of each table, `{table}` standing once for its identifier. |
| `mercure.private` | `true` | Publishes privately, to the subscribers the hub authorized for the topic only. |
| `view.resolver` | `null` | The service implementing `ViewerResolver`, which names the seat of the current player. |
| `view.audience` | `false` | Without a resolver, lets anyone read the audience view of every table; no one reads a table otherwise. |
| `roleplaying_scene` | `false` | Registers the library's ready game for the phases of a role-playing scene. |
| `translation_domain` | `tilttek_game_engine` | The translation domain of the template's texts. |
| `kit_subdirectory` | `null` | The subdirectory of the components where the recipes of the kit are installed; that of the Symfony UX Toolkit when `null`. |

A lock factory or a hub named while its component is missing stops the container from building, with the package to
install.
