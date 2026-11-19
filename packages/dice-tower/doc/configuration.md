# Configuration

```yaml
# config/packages/tilttek_dice_tower.yaml
tilttek_dice_tower:
    history: 20
    topic_prefix: 'urn:tilttek:dice-tower:'
    table_access: ~
    hub: Symfony\Component\Mercure\HubInterface
    hub_name: ~
    message_bus: ~
    store: doctrine
    entity_manager: default
    kit_subdirectory: ~
```

| Key | Default | What it sets |
|---|---|---|
| `history` | `20` | How many entries a viewer reads at once, from 1 to 200: the last ones when the overlay is drawn, and those missed when the connection comes back |
| `topic_prefix` | `urn:tilttek:dice-tower:` | What comes before a table's reference, URL-encoded, in the topic of its tower |
| `table_access` | `null` | The service, a `TableAccess`, that says whether the current viewer may watch a table; nobody may when null |
| `hub` | `Symfony\Component\Mercure\HubInterface` | The service of the Mercure hub each new entry is pushed to; null, or a service that does not exist, pushes nothing |
| `hub_name` | `null` | The name of the hub the authorization cookie is for, when the application has several |
| `message_bus` | `null` | The message bus the relays are reached through; they are called during the roll when null |
| `store` | `doctrine` | Where the log is kept: `doctrine`, or `memory` for tests |
| `entity_manager` | `default` | The Doctrine entity manager of the log |
| `kit_subdirectory` | `null` | Where the recipes of the UX kit are, under the components directory; null takes the subdirectory of the `ux_toolkit` configuration |

The topics are private: the hub delivers an entry only to a subscriber whose token names the table's topic. The
bundle gives that token, in the authorization cookie of `symfony/mercure-bundle`, to an allowed viewer when the overlay
is drawn, for that table only.

The overlay reads the subdirectory of the Symfony UX Toolkit when the container is compiled. In an environment where
the Toolkit is not enabled, as production often is, repeat it in `kit_subdirectory`.

The services keep no state between requests: the log is read from its store on each request, and the memory store is
emptied between two requests of a long-running process.
