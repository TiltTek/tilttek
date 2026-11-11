# Configuration

```yaml
tilttek_badges:
    store: doctrine              # or memory, for tests
    connection: default          # the DBAL connection of the tables
    timezone: UTC                # the days of a streak and the dates of a period
    badges: {}                   # by code, see below
    projection: badges           # the name of the projection's checkpoint
    showcase_size: 6             # badges a member features, at most
    max_awards_per_activity: 10  # awards of a repeatable badge one activity brings, at most
    translation_domain: badges   # the domain of the badges' names and descriptions
    open_badges:
        enabled: false           # needs phil/open-badges-bundle, which holds the issuer and its key
        achievement_url: ~       # e.g. https://example.org/badges/{code}
    privacy:
        member_kind: member      # a data subject 44 is the member member:44
```

## Badges

Each badge, by code, takes `name`, `description`, `image`, `repeatable`, `hidden`, `revocable`, `tier` (`series`,
`rank`) and its `criterion`, one of:

| Criterion | Keys |
|---|---|
| `count` | `verbs`, `times`, and `context`, `since`, `until` |
| `first` | `verbs`, and `context`, `since`, `until` |
| `total` | `verbs`, `amount`, and `context`, `since`, `until` |
| `streak` | `verbs`, `days`, and `context`, `since`, `until` |
| `measure` | `name`, `threshold`, `verbs` — read through the application's `Tilttek\Badges\Measures` service |
| `manual` | `true` |
| `all` | a list of criteria |
| `at_least` | `count`, and `of`, a list of criteria |

```yaml
tilttek_badges:
    badges:
        commenter.silver:
            name: badges.commenter.silver.name
            description: badges.commenter.silver.description
            image: /images/badges/commenter-silver.svg
            tier: { series: commenter, rank: 2 }
            revocable: true
            criterion: { count: { verbs: ['news.commented'], times: 50 } }
        december:
            name: badges.december.name
            hidden: true
            criterion: { first: { verbs: ['event.*'], since: '2026-12-01', until: '2027-01-01' } }
```

The catalog is read when the container is compiled: a mistake fails the build with the library's `InvalidBadge`.

## Badges in the database

The catalog is the service `Tilttek\Badges\Catalog`, built from this configuration. An application that edits its
badges in the database replaces that service with its own factory returning a `Catalog` — `Catalog::fromArray()` reads
the same plain values from rows.

The services keep no state between requests: they are safe in a worker. The stores in memory, for tests, empty
themselves when the kernel resets, and the projection forgets what its last rebuild kept for the replay.
