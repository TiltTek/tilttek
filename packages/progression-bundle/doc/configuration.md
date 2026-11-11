# Configuration

```yaml
tilttek_progression:
    store: doctrine            # or memory, for tests
    connection: default
    timezone: America/Toronto  # caps, calendar seasons, and a member's day when nothing says theirs
    tracks:
        player:
            curve: { type: quadratic, base: 50, levels: 60 }
            subject: member    # or object: the gesture's object, such as a character
            seasons: { length: quarter }
            rules:
                games: { verbs: ['game.played'], experience: 20, cap: { amount: 100, period: day } }
                wins: { verbs: ['game.won'], experience: 5, per_quantity: false, context: { ranked: true } }
            milestones:
                veteran: { level: 10 }
                thousand: { experience: 1000 }
        summer-event:
            curve: { type: table, thresholds: [100, 300, 600] }
            seasons:
                named:
                    - { code: summer-2026, start: '2026-06-21', end: '2026-09-22' }
            rules: { games: { verbs: ['game.*'], experience: 10 } }
    streaks:
        daily-play: { verbs: ['game.played'], unit: day, grace: { allowed: 1, window: 7 } }
        weekly-writing: { verbs: ['page.written'], unit: week }
    timezone_context: timezone # the context key that carries the member's timezone; null for the configured one
    projection: progression
    translation_domain: tilttek_progression
    privacy:
        member_kind: member
```

| Option | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | `doctrine` or `memory` |
| `connection` | `default` | The DBAL connection of the five tables |
| `timezone` | `UTC` | The timezone of caps, calendar seasons and members' days |
| `tracks.<name>.curve` | required | `type` (`linear`, `quadratic`, `exponential`, `table`), `step`, `base`, `factor`, `levels` (2 to 1000, 100 by default), `thresholds` |
| `tracks.<name>.subject` | `member` | `member` or `object` |
| `tracks.<name>.seasons` | none | `length` (`month`, `quarter`, `year`) or `named` (a list of `code`, `start`, `end`) |
| `tracks.<name>.rules.<name>` | — | `verbs` (required), `experience` (required, at least 1), `per_quantity` (true), `cap` (`amount`, `period`: `day`, `week`, `month`, `year`, `ever`), `context` |
| `tracks.<name>.milestones.<code>` | — | `experience` (at least 1) or `level` (at least 2) |
| `streaks.<name>` | — | `verbs` (required), `unit` (`day` or `week`), `grace` (`allowed` at least 1, `window` at least 2), `context` |
| `timezone_context` | null | The key of the activity's context holding the member's timezone |
| `projection` | `progression` | The name the projection's checkpoint is kept under |
| `translation_domain` | `tilttek_progression` | The domain of the templates' texts |
| `privacy.member_kind` | `member` | The kind of a data subject's member reference |

Names of tracks, rules, milestones and streaks are kept as written — `daily-play` stays `daily-play` —; context names
follow Symfony's normalisation, `the-role` becoming `the_role`. A curve missing its step, base, factor or thresholds, a
milestone level beyond the curve, a grace that holds no gap, a timezone that is none fail when the container is
compiled, with the library's `InvalidProgression`; a value of the wrong kind or out of range fails with Symfony's
`InvalidConfigurationException`.

The services keep no state between requests: they are safe in a worker. The stores in memory, for tests, empty
themselves when the kernel resets, and the projection forgets what its last rebuild kept for the replay.
