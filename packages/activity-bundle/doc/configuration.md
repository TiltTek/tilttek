# Configuration

```yaml
tilttek_activity:
    store: doctrine            # or memory, for tests
    connection: default        # the DBAL connection of the journal
    verbs: []                  # the declared verbs, as patterns; empty accepts every verb
    batch_size: 500            # entries read at a time by a projection, and by the export of a member
    projections: live          # live, messenger or manual
    projection_bus: messenger.bus.default   # the bus of the projections' messages, in messenger mode
    dates:
        past: P30D             # how long before now a gesture may be dated; ~ for no bound
        ahead: PT5M            # how long after now a gesture may be dated
    privacy:
        member_kind: member    # a data subject "44" is the member "member:44"
```

| Key | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | `doctrine` keeps the journal and the checkpoints in the database; `memory` keeps them in the process, for tests |
| `connection` | `default` | The DBAL connection, `doctrine.dbal.<name>_connection`; the schema listener is tied to it |
| `verbs` | `[]` | Patterns such as `game.played`, `game.*` or `*`; a wrong pattern fails when the container is compiled; an undeclared verb raises `UnknownVerb` when it is recorded |
| `batch_size` | `500` | At least 1 |
| `projections` | `live` | `live`: each new entry catches up the projections that read it, in the same request; `messenger`: it sends them a `CatchUpProjection` message ([Projections](projections.md)); `manual`: only `tilttek:activity:catch-up` catches them up |
| `projection_bus` | `messenger.bus.default` | The Messenger bus the messages are sent on and handled from, in `messenger` mode |
| `dates.past` | `P30D` | An ISO 8601 duration: a gesture dated earlier before the clock raises `InvalidActivity`; `~` lifts the bound, for an application that imports a history |
| `dates.ahead` | `PT5M` | An ISO 8601 duration: a gesture dated later after the clock raises `InvalidActivity` |
| `privacy.member_kind` | `member` | The kind of the reference a data subject of `phil/privacy-bundle` stands for |

The services keep no state between requests: they are safe in a worker. The journal and the checkpoints in memory, for
tests, empty themselves when the kernel resets.
