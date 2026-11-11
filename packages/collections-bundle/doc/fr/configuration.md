<!-- phil-dev source-sha256=28e1b7b918955daf2b08ee26856e3e10982722cc4134e7732e02d3146a54baf5 -->
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

Les clés de `rarities`, `items`, `sets`, `packs` et `daily.dates` sont gardées telles qu'écrites, tirets compris. Les
définitions sont celles que lit `CatalogReader`, telles que les décrit la page du catalogue de `tilttek/collections`.

**Ce qui échoue à la compilation du conteneur :** une définition ou une référence erronée dans le catalogue, un fuseau
horaire qui n'en est pas un, une date fixe qui n'en est pas une, une date calculée que `phil/almanac` ne sait pas lire
— ou toute date calculée sans `phil/almanac` —, un catalogue configuré ici avec `catalog: doctrine`, et un objet du
jour activé sans verbes.

Le `salt` est un secret : qui le connaît, avec le catalogue, calcule l'objet de chaque jour à venir. Donnez-lui au moins
32 octets aléatoires tirés d'une variable d'environnement ou d'un secret, ou omettez-le : le sel est alors une étiquette
et `kernel.secret`. Un `salt` de moins de 32 octets est refusé. Changez le `salt`, et les jours libres du calendrier
tirent d'autres objets ; les jours déjà générés gardent les leurs.

Les services ne gardent aucun état entre les requêtes : ils sont sûrs dans un *worker*. Les stockages en mémoire, pour
les tests, se vident quand le noyau se réinitialise.
