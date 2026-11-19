# Configuration

```yaml
tilttek_exchange:
    store: doctrine                # or memory, for tests
    connection: default            # the DBAL connection of the tables
    catalog: configuration         # or doctrine: the table tilttek_exchange_reward
    rewards:
        discount-10: { price: 100 }
        seat:
            price: 250
            currency: stars        # the default currency of tilttek/points when null
            stock: 30              # every unit the reward ever has; unlimited when null
            available_from: '2026-11-01'
            available_until: '2026-12-01'
            per_member: 1
            requires: ['badge:helper']
    reservation_lifetime: PT30M    # ISO 8601
    requirements: ~                # service implementing Tilttek\Exchange\Reward\Requirements
    member_since: ~                # service implementing Tilttek\Exchange\MemberSince
    timezone: UTC                  # where the periods of the allowances are counted
    transfers:
        enabled: false
        allowance: { limit: 500, period: week }   # day, week, month, year or ever; none when left out
        new_member_delay: ~        # ISO 8601, like P7D
    trades:
        enabled: false
        inventory: ~               # service implementing Tilttek\Exchange\Trade\Inventory
        offer_lifetime: P7D
        allowance: { limit: 10, period: day }
        new_member_delay: ~
    privacy:
        member_kind: member        # a data subject 44 is the member member:44
```

| Key | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | Where the reservations, transfers and offers are kept: `doctrine`, or `memory` for tests. |
| `connection` | `default` | The DBAL connection of the tables. |
| `catalog` | `configuration` | The rewards come from `rewards`, or from the table `tilttek_exchange_reward` with `doctrine`, which needs the `doctrine` store and an empty `rewards`. |
| `rewards` | none | The rewards by code — the code is kept as written, dashes included —, as [Rewards](rewards.md) describes them. |
| `reservation_lifetime` | `PT30M` | How long a reservation waits for its confirmation. |
| `requirements` | none | The service checking the rewards' conditions; without it, no condition is met. |
| `member_since` | none | The service telling when a member joined; needed by a `new_member_delay`. |
| `timezone` | `UTC` | The timezone of the allowances' periods. |
| `transfers.enabled` | `false` | Whether members give each other points; `Transfers` and the journal command exist only then. |
| `trades.enabled` | `false` | Whether members trade items; `Trades` exists only then, and needs `trades.inventory`. |
| `*.allowance` | none | The most a member gives over a period: points for transfers, offers for trades. |
| `*.new_member_delay` | none | How long an account waits before it exchanges with others. |
| `trades.offer_lifetime` | `P7D` | How long an offer waits for its answer. |
| `privacy.member_kind` | `member` | The kind of the member reference a data subject stands for. |

When the container is compiled, a reward that does not hold, a duration or a timezone that is none, a catalogue in the
database without the `doctrine` store, a delay without `member_since` and trades without an inventory are refused with
`InvalidExchange`.

The services keep no state between requests: they are safe in a worker. The stores in memory, for tests, empty
themselves when the kernel resets.
