# Configuration

```yaml
# config/packages/tilttek_luck_tokens.yaml
tilttek_luck_tokens:
    store: doctrine               # or memory, for tests
    connection: default           # the DBAL connection of the ledgers and of the rules kept there
    rules: configuration          # or doctrine, or service
    rules_service: ~              # with rules: service
    tables:
        'table:12':
            game_masters: ['member:7']
            kinds:
                - { key: inspiration, name: Inspiration, cap: 1, transferable: true }
                - { key: bennies, name: Bennies, cap: 5, scope: player, refresh: each-session, refresh_to: 3,
                    carry_over: 0, grants: anyone, transferable: true }
    clock: clock                  # the service of a Psr\Clock\ClockInterface
    max_entries: 100000           # the most entries the ledger of a table takes
    holder_access: self           # or security, or service
    holder_access_service: ~      # with holder_access: service
    lock:
        enabled: false
        factory: lock.factory     # the service of a Symfony\Component\Lock\LockFactory
        scope: holder             # or table
        ttl: 30.0                 # seconds, should the process die holding a lock
    privacy:
        member_kind: member       # a data subject 44 is the member member:44
```

| Key | Default | What it does |
|---|---|---|
| `store` | `doctrine` | Where the ledgers are kept: `doctrine` — `DbalTokenLedger` — or `memory` — the library's `InMemoryTokenLedger`, for tests and scripts. |
| `connection` | `default` | The DBAL connection, `doctrine.dbal.<name>_connection`. |
| `rules` | `configuration` | `configuration`: the tables below, read once by `ConfiguredTokenRules`. `doctrine`: `DbalTokenRules`, which the application fills from the settings of each table; it needs the `doctrine` store. `service`: the service named by `rules_service`. |
| `rules_service` | null | The id of a service implementing `Tilttek\LuckTokens\Contract\TokenRules`; required with `rules: service`. |
| `tables` | none | The rules of each table, by its reference: its game masters and its kinds, as `TableRules::fromArray()` reads them. Only with `rules: configuration`. |
| `clock` | `clock` | The clock of the entries; FrameworkBundle registers `clock`. |
| `max_entries` | 100 000 | The size of a ledger, 1 or more. |
| `holder_access` | `self` | `self`: each holder spends and passes their own tokens. `security`: the security voters decide, see [Who controls whom](holder-access.md). `service`: the service named by `holder_access_service`. The game masters of a table always may. |
| `holder_access_service` | null | The id of a service implementing `Tilttek\LuckTokens\Contract\HolderAccess`; required with `holder_access: service`. |
| `lock.enabled` | false | Registers `LockedLuckTokens`, see [Locks](locks.md). |
| `lock.factory` | `lock.factory` | The lock factory; FrameworkBundle registers `lock.factory` once `framework.lock` is set. |
| `lock.scope` | `holder` | `holder` or `table`. |
| `lock.ttl` | 30.0 | How long a lock lives at most, 1 second or more. |
| `privacy.member_kind` | `member` | The kind of the reference a data subject of `phil/privacy-bundle` stands for. |

A kind of token takes `key`, `name` and `cap`, and optionally `scope` (`character` or `player`), `refresh` (`never` or
`each-session`), `refresh_to`, `carry_over`, `grants` (`game-masters` or `anyone`) and `transferable`; a key left out
takes the library's default.

## Checked when the container is compiled

The container refuses, rather than the first operation:

- tables that are not rules the library takes — a kind without a cap, a refresh amount on a kind never refreshed, a
  reference that is none —, with `InvalidTokenRule`;
- `tables` given while the rules come from elsewhere, rules in the database with the `memory` store, `service` without
  the id of the service, `security` without the Security component and the locks without the Lock component, with
  `InvalidConfigurationException`.
