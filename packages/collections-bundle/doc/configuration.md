# Configuration

```yaml
tilttek_collections:
    store: doctrine              # or memory, for tests
    connection: default          # the Doctrine DBAL connection
    timezone: UTC                # the timezone of the calendar of the item of the day
    locale: '%kernel.default_locale%' # the locale the dice a member rolls are named in
    catalog: configuration       # or doctrine: the catalog in tilttek_collections_definition
    rarities: { common: 70, rare: { weight: 25 }, legendary: 5 }
    items:
        pirate: { name: { en: Pirate, fr: Pirate }, rarity: rare, sets: [crew], image: /img/pirate.webp, description: Sails the seas. }
        ember-d6: { name: Ember d6, rarity: common, sets: [ember], die: { sides: 6, color: '#c0392b', material: resin } }
        medal: { name: Medal, rarity: rare, tradable: false }
    sets:
        crew: { name: The crew, kind: plain, rewards: [{ kind: points, code: xp, amount: 50 }] }
        ember: { name: Ember dice, kind: base }
    packs:
        booster: { name: Booster, size: 5, sets: [crew], weights: { common: 80, rare: 20 } }
    daily:
        enabled: false           # whether the activity gives the item of the day
        verbs: []                # the verb patterns of the activity that counts as coming by
        salt: '%env(COLLECTIONS_DAILY_SALT)%' # a secret of 32 bytes or more; derived from kernel.secret when left out
        sets: []                 # the sets the free days draw from; every item when none is named
        dates: { '10-31': pumpkin, '2026-12-25': star }
        computed: [{ rule: easter, item: egg }, { rule: 'fourth thursday of november', item: turkey, days: 4 }]
        projection: collections.daily
    translation_domain: tilttek_collections
    privacy:
        member_kind: member      # the kind of the member a data subject of phil/privacy-bundle stands for
```

The keys of `rarities`, `items`, `sets`, `packs` and `daily.dates` are kept as written, dashes included. The
definitions are those `CatalogReader` reads, as the catalog page of `tilttek/collections` describes them.

**What fails when the container is compiled:** a wrong definition or reference in the catalog, a timezone that is
none, a fixed date that is none, a computed date `phil/almanac` cannot read — or any computed date without
`phil/almanac` —, a catalog configured here with `catalog: doctrine`, and an item of the day enabled without verbs.

The `salt` is a secret: whoever knows it, with the catalog, computes the item of every day to come. Give it at least
32 random bytes from an environment variable or a secret, or leave it out: the salt is then a label and
`kernel.secret`. A salt shorter than 32 bytes is refused. Change the `salt` and the free days of the calendar draw
other items; the days already generated keep theirs.

The services keep no state between requests: they are safe in a worker. The stores in memory, for tests, empty
themselves when the kernel resets.
