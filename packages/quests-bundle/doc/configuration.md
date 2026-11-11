# Configuration

```yaml
tilttek_quests:
    store: doctrine               # or memory, for tests
    connection: default           # the Doctrine DBAL connection
    timezone: America/Toronto     # the timezone the quests' dates are read in
    catalog: configuration        # or doctrine: the quests are kept in the database
    quests:
        first-steps:
            ordered: true
            steps:
                - { code: play, verbs: ['game.played'], count: 3 }
                - { code: write, verbs: ['sheet.published'] }
            rewards: [{ kind: points, code: xp, amount: 50 }, { kind: badge, code: newcomer }]
        winter-reading:
            admission: accept
            recurs: { from: '12-21', until: '03-20' }
            steps: [{ verbs: ['book.read'], total: 300 }]
        thousand-games:
            kind: community
            opens: '2026-12-01'
            closes: '2027-01-01'
            steps: [{ verbs: ['game.played'], count: 1000 }]
        raid:
            kind: team
            steps: [{ verbs: ['dungeon.cleared'], count: 5 }]
        ten-games:
            kind: duel
            duel: { length: P7D }
            steps: [{ verbs: ['game.played'], count: 10 }]
    duels:
        offer_lifetime: P7D       # how long a duel offer waits for its answer
    projection: quests            # the name of the projection's checkpoint
    translation_domain: tilttek_quests
    privacy:
        member_kind: member       # a data subject's value is the identifier of member:<value>
```

Each quest is the definition `QuestReader` reads — see the library's documentation on quests and steps, schedules and
chains, and genres. A wrong quest, a broken chain or an unknown timezone fails when the container is compiled, with the
library's message. The quests are configured here or kept in the database — `catalog: doctrine` —, not both.

## Quests that come back

With `phil/almanac` installed, the key `recurs` makes a quest come back each year at dates the almanac computes, whole
days in the configured timezone:

```yaml
recurs: { from: 'easter - 2 days', until: 'easter + 1 day' }   # from Good Friday to Easter Monday
recurs: { from: '10-25', days: 7 }                              # a week from October 25
recurs: { from: 'winter solstice', days: 3 }
```

`from` is a rule in the almanac's words; the quest lasts `days` days — one by default — or `until` the first day of
another rule. Each occurrence is named by the date it opens on, such as `2027-03-26`, and the quest is accomplished
once per occurrence.

The services keep no state between requests: they are safe in a worker. The stores in memory, for tests, empty
themselves when the kernel resets, and the projection forgets what its last rebuild kept for the replay.
