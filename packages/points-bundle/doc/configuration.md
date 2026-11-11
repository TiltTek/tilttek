# Configuration

```yaml
tilttek_points:
    store: doctrine              # or memory, for tests
    connection: default          # the DBAL connection of the ledger
    currencies:                  # by code, with the lifetime of their credits
        points: ~                # never expires
        stars: P1Y               # each credit expires a year after its moment
    default_currency: ~          # the first currency when null
    timezone: UTC                # the timezone the caps' periods are counted in
    rules:
        games:
            verbs: ['game.played']
            points: 10
            currency: ~          # the default currency when null
            per_quantity: true   # points per unit of the activity's quantity, or once per activity
            cap: { amount: 50, period: week }   # day, week, month, year or ever
            context: { system: dnd5e }          # facts the activity's context must hold
    projection: points           # the projection's name, its checkpoint's name in tilttek/activity-bundle
    batch_size: 500              # movements read at a time by the expiry and the export of a member
    privacy:
        member_kind: member      # a data subject "44" is the member "member:44"
```

| Key | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | `doctrine` keeps the ledger in the database; `memory` keeps it in the process, for tests |
| `connection` | `default` | The DBAL connection, `doctrine.dbal.<name>_connection`; the schema listener is tied to it |
| `currencies` | `{points: ~}` | At least one; a lifetime is an ISO 8601 duration (`P6M`, `P1Y`), written as the value or as `{lifetime: P1Y}` |
| `default_currency` | `null` | A code among the currencies; an unknown one fails when the container is compiled |
| `timezone` | `UTC` | A timezone identifier; a wrong one fails when the container is compiled |
| `rules` | `{}` | By name; `verbs` (at least one) and `points` (at least 1) are required; a rule in an unknown currency, a wrong name, verb pattern or period fails when the container is compiled; a context condition is an integer, a boolean or a string |
| `projection` | `points` | Change it to run two point systems side by side |
| `batch_size` | `500` | At least 1 |
| `privacy.member_kind` | `member` | The kind of the reference a data subject of `phil/privacy-bundle` stands for |

The services keep no state between requests: they are safe in a worker. The ledger in memory, for tests, empties itself
when the kernel resets.
