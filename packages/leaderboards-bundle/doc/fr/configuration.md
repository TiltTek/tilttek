<!-- phil-dev source-sha256=8aed1e44325b437ff8dd4ca2cb2ac8291242f599f8acf7dc5e0b1cbfa6a97b24 -->
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

| Option | Valeur par défaut | Ce qu'elle dit |
|---|---|---|
| `store` | `doctrine` | Où sont gardés les classements : Doctrine DBAL, ou la mémoire pour les tests |
| `valkey.enabled` | `false` | Si les contributions et les scores sont gardés dans Valkey ; les instantanés, les choix et les ligues restent dans `store` |
| `valkey.dsn` | `redis://localhost:6379` | Le serveur Valkey, tel que `RedisAdapter::createConnection()` de Symfony Cache le lit, avec l'extension redis, Relay ou Predis |
| `valkey.client` | `null` | Le service d'un client Valkey que l'application a déjà, utilisé à la place de `dsn` : `Redis` ou `RedisCluster` de l'extension redis, un client Relay, un client Predis, ou `ValkeyGlide` de valkey-glide-php ; Symfony Cache n'est alors pas nécessaire |
| `valkey.prefix` | `tilttek_leaderboards` | Le préfixe des clés, entre accolades, une étiquette de hachage qui les garde sur un seul nœud d'une grappe |
| `connection` | `default` | La connexion DBAL des neuf tables |
| `timezone` | `UTC` | Le fuseau horaire des périodes des classements qui n'en nomment aucun |
| `boards.<name>.measure` | — | Ce sur quoi porte le classement ; obligatoire |
| `boards.<name>.verbs` | `[]` | Les verbes lus dans le journal, sous forme de motifs ; au moins un pour `count`, `total`, `best` et `days`, aucun pour `latest` et `highest` |
| `boards.<name>.context` | `{}` | Les faits que le contexte d'une activité doit contenir pour compter : entiers, booléens ou chaînes |
| `boards.<name>.period` | `ever` | La période sur laquelle porte le classement |
| `boards.<name>.window` | `0` | Les jours d'une fenêtre glissante, aujourd'hui compris, pour un classement qui porte sur une fenêtre : de 1 à 366 |
| `boards.<name>.timezone` | `null` | Le fuseau horaire propre au classement |
| `boards.<name>.seasons` | `[]` | Les saisons d'un classement par saison, chacune avec son `code`, son `start` et son `end` |
| `boards.<name>.ties` | `first` | Les scores égaux, après les critères de départage, au premier qui les a atteints, ou des rangs `shared` |
| `boards.<name>.tie_breaks` | `[]` | Jusqu'à deux mesures qui départagent les scores égaux : chacune avec une `measure` (`count`, `total`, `highest`, `lowest`, ou `reported` dans un classement rapporté), un `order`, `desc` (la valeur par défaut) ou `asc`, et un entier de `context` lu à la place de la quantité |
| `boards.<name>.per_object` | `false` | Si le classement classe les membres pour chaque objet des activités |
| `boards.<name>.title` | aucun | Un titre (son `name`, son `threshold` et `leader`, faux par défaut) détenu tant qu'un score dans la période en cours atteint le seuil, par le seul meneur avec `leader` |
| `boards.<name>.chance` | aucune | La chance ajoutée au score de chaque équipe : `dice` (de 1 à 10, 1 par défaut) dés de `faces` (de 2 à 100, 6) faces |
| `boards.<name>.snapshot_size` | `100` | Combien de membres garde un instantané |
| `boards.<name>.league.tiers` | — | Les paliers de la ligue du classement, à partir du plus bas ; obligatoire avec une ligue |
| `boards.<name>.league.division_size` | `30` | Combien de membres compte une division |
| `boards.<name>.league.promoted` | `7` | Combien des premiers d'une division montent d'un palier |
| `boards.<name>.league.relegated` | `5` | Combien des derniers d'une division pleine descendent d'un palier |
| `boards.<name>.league.formed_by` | `arrival` | Comment se remplissent les divisions d'un palier : dans l'ordre d'`arrival`, ou selon le `volume` de la semaine précédente |
| `projection` | `leaderboards` | Le nom de la projection |
| `schedule.enabled` | `false` | Si Symfony Scheduler prend les instantanés et règle les semaines dus ([Planification](scheduling.md)) |
| `schedule.frequency` | `1 hour` | À quelle fréquence, telle que Symfony Scheduler lit une fréquence |
| `secret` | `%kernel.secret%` | Le secret d'où se tire la chance des équipes |
| `kit_subdirectory` | `null` | Où sont les recettes du kit, sous le répertoire des composants ; le sous-répertoire de la configuration `ux_toolkit` quand il est null ([Gabarits](templates.md)) |
| `celebration.enabled` | `true` | Si `tilttek_leaderboards_celebration()` dit à un membre qu'il a atteint le sommet ; faux la fait retourner null et le gabarit de célébration ne produit aucun rendu ([Gabarits](templates.md)) |
| `celebration.effect` | `confetti` | Ce que dessine la recette `Celebration` du kit là où le mouvement n'est pas réduit : `confetti` ou `fireworks` |
| `celebration.celebrate_on` | `period_close` | Quand une première place est dite et célébrée : `period_close`, pour qui a fini premier de l'instantané une fois la période close ; `score_change`, pour qui prend la tête de la période en cours à mesure que son score change ([Événements](events.md)). Une promotion dans le palier le plus haut est dite au règlement de sa semaine dans les deux cas. Elle s'applique aux événements `TopReached` même quand `celebration.enabled` est faux |
| `translation_domain` | `tilttek_leaderboards` | Le domaine des textes du gabarit et des noms des classements, `boards.<name>` |
| `privacy.member_kind` | `member` | Le genre de la référence de membre que représente une personne concernée |

Les noms des classements sont gardés tels qu'écrits (`games-month` reste `games-month`) ; les clés du contexte suivent
la normalisation de Symfony, `first-time` devenant `first_time`. Un classement incohérent échoue à la compilation du
conteneur : une mesure du journal sans verbes, une saison hors d'un classement par saisons, un fuseau horaire qui n'en
est pas un, une ligue sur un classement qui n'est pas hebdomadaire ou qui déplace plus de membres que n'en compte une
division, une fenêtre sur un classement qui ne porte pas sur une fenêtre, un critère de départage rapporté sur un
classement lu à partir du journal.

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Les stockages en mémoire,
pour les tests, se vident quand le noyau se réinitialise.
