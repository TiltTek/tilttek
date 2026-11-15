# Configuration

```yaml
tilttek_leaderboards:
    store: doctrine              # or memory, for tests
    valkey:
        enabled: true            # or `valkey: true`; false by default
        dsn: 'redis://localhost:6379'   # read by Symfony Cache
        # client: app.valkey     # or the service of a client the application has, instead of the DSN
        prefix: tilttek_leaderboards
    connection: default          # the Doctrine DBAL connection
    timezone: America/Toronto    # the timezone of the boards that name none
    boards:
        games-month:
            measure: count       # count, total, best, days — from the journal —; latest, highest — reported
            verbs: ['game.played', 'game.won']
            context: { ranked: true }
            period: month        # day, week, month, quarter, year, season, ever (the default), window
            ties: shared         # first (the default) or shared
            tie_breaks:          # up to two, in turn, before the ties
                - { measure: count, order: asc }                      # count, total, highest, lowest, reported
                - { measure: lowest, order: asc, context: seconds }   # a context integer instead of the quantity
            snapshot_size: 10    # 1 to 1000, 100 by default
        summer:
            measure: total
            verbs: ['quest.*']
            period: season
            timezone: Europe/Paris
            seasons:
                - { code: summer-2026, start: '2026-06-21', end: '2026-09-22' }
        level:
            measure: latest
        weekly-xp:
            measure: total
            verbs: ['lesson.done']
            period: week         # a league needs a weekly board
            league:
                tiers: [bronze, silver, gold, sapphire, diamond]   # from the lowest
                division_size: 30   # 2 to 100
                promoted: 7         # the first of a division who go up
                relegated: 5        # the last of a full division who go down
                formed_by: volume   # arrival (the default) or volume of the week before
        regular:
            measure: days
            verbs: ['lesson.done']
            period: window
            window: 7            # the days of the window, today included: 1 to 366
            title: { name: regular, threshold: 5 }
        game-records:
            measure: best
            verbs: ['game.scored']
            per_object: true     # a ranking for each object of the activities
        house-cup:
            measure: total
            verbs: ['quest.done']
            period: month
            chance: { dice: 2, faces: 6 }   # the luck added to the score of a team
    projection: leaderboards     # the name of the projection's checkpoint
    schedule:
        enabled: true            # or `schedule: true`; false by default
        frequency: '1 hour'      # how often the snapshots and settlements due are looked for
    secret: '%kernel.secret%'    # the secret the luck of the teams is drawn from
    kit_subdirectory: ~          # where the recipes of the kit are; the Toolkit's subdirectory when null
    celebration:
        enabled: true            # or `celebration: false`; true by default
        effect: confetti         # or fireworks
        celebrate_on: period_close   # or score_change
    translation_domain: tilttek_leaderboards
    privacy:
        member_kind: member      # the kind of a member's reference, for phil/privacy-bundle
```

| Option | Default | What it says |
|---|---|---|
| `store` | `doctrine` | Where the boards are kept: Doctrine DBAL, or memory for tests |
| `valkey.enabled` | `false` | Whether the contributions and the scores are kept in Valkey; the snapshots, choices and leagues stay in `store` |
| `valkey.dsn` | `redis://localhost:6379` | The Valkey server, as Symfony Cache's `RedisAdapter::createConnection()` reads it, with the redis extension, Relay or Predis |
| `valkey.client` | `null` | The service of a Valkey client the application already has, used instead of `dsn`: `Redis` or `RedisCluster` of the redis extension, a Relay client, a Predis client, or `ValkeyGlide` of valkey-glide-php; Symfony Cache is then not needed |
| `valkey.prefix` | `tilttek_leaderboards` | The prefix of the keys, between braces, a hash tag that keeps them on one node of a cluster |
| `connection` | `default` | The DBAL connection of the nine tables |
| `timezone` | `UTC` | The timezone of the periods of the boards that name none |
| `boards.<name>.measure` | — | What the board ranks on; required |
| `boards.<name>.verbs` | `[]` | The verbs read from the journal, as patterns; at least one for `count`, `total`, `best` and `days`, none for `latest` and `highest` |
| `boards.<name>.context` | `{}` | The facts an activity's context must hold to count: integers, booleans or strings |
| `boards.<name>.period` | `ever` | The period the board ranks over |
| `boards.<name>.window` | `0` | The days of a sliding window, today included, for a board ranked over one: 1 to 366 |
| `boards.<name>.timezone` | `null` | The board's own timezone |
| `boards.<name>.seasons` | `[]` | The seasons of a board ranked by season, each with its `code`, `start` and `end` |
| `boards.<name>.ties` | `first` | Equal scores, after the tie-breaks, to the first who reached them, or `shared` ranks |
| `boards.<name>.tie_breaks` | `[]` | Up to two measures that order equal scores: each a `measure` — `count`, `total`, `highest`, `lowest`, or `reported` on a reported board —, an `order`, `desc` (the default) or `asc`, and a `context` integer read instead of the quantity |
| `boards.<name>.per_object` | `false` | Whether the board ranks members for each object of the activities |
| `boards.<name>.title` | none | A title — its `name`, `threshold` and `leader`, false by default — held while a score in the period under way reaches the threshold, by the leader alone with `leader` |
| `boards.<name>.chance` | none | The luck added to the score of each team: `dice` (1 to 10, 1 by default) of `faces` (2 to 100, 6) |
| `boards.<name>.snapshot_size` | `100` | How many members a snapshot keeps |
| `boards.<name>.league.tiers` | — | The tiers of the board's league, from the lowest; required with a league |
| `boards.<name>.league.division_size` | `30` | How many members a division holds |
| `boards.<name>.league.promoted` | `7` | How many of the first of a division go up a tier |
| `boards.<name>.league.relegated` | `5` | How many of the last of a full division go down a tier |
| `boards.<name>.league.formed_by` | `arrival` | How a tier's divisions are filled: in the order of `arrival`, or by the `volume` of the week before |
| `projection` | `leaderboards` | The name of the projection |
| `schedule.enabled` | `false` | Whether Symfony Scheduler takes the snapshots and settles the weeks due ([Scheduling](scheduling.md)) |
| `schedule.frequency` | `1 hour` | How often, as Symfony Scheduler reads a frequency |
| `secret` | `%kernel.secret%` | The secret the luck of the teams is drawn from |
| `kit_subdirectory` | `null` | Where the recipes of the kit are, under the components directory; the subdirectory of the `ux_toolkit` configuration when null ([Templates](templates.md)) |
| `celebration.enabled` | `true` | Whether `tilttek_leaderboards_celebration()` tells a member they reached the top; false makes it return null and the celebration template render nothing ([Templates](templates.md)) |
| `celebration.effect` | `confetti` | What the kit's `Celebration` draws where motion is not reduced: `confetti` or `fireworks` |
| `celebration.celebrate_on` | `period_close` | When a first place is told and celebrated: `period_close`, for who finished first of the snapshot once the period closed; `score_change`, for who takes the lead of the period under way as their score changes ([Events](events.md)). A promotion into the highest tier is told when its week is settled either way. It applies to the `TopReached` events even when `celebration.enabled` is false |
| `translation_domain` | `tilttek_leaderboards` | The domain of the template's texts and of the boards' names, `boards.<name>` |
| `privacy.member_kind` | `member` | The kind of the member reference a data subject stands for |

The boards' names are kept as written — `games-month` stays `games-month` —; the context's keys follow Symfony's
normalisation, `first-time` becoming `first_time`. A board that does not hold together — a journal measure without
verbs, a season out of a seasons board, a timezone that is none, a league on a board that is not weekly or that
moves more members than a division holds, a window on a board that is not ranked over one, a reported tie-break on a
board read from the journal — fails when the container is compiled.

The services keep no state between requests: they are safe in a worker. The stores in memory, for tests, empty
themselves when the kernel resets.
