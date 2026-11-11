<!-- phil-dev source-sha256=08f807c002e724df365a95a2b2183588bed84e1b75ae3550c9a25b24d73a3d4d -->
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

Chaque quête est la définition que lit `QuestReader`. Consultez la documentation de la bibliothèque sur les quêtes et
les étapes, les calendriers et les chaînes, et les genres. Une quête erronée, une chaîne brisée ou un fuseau horaire
inconnu échoue à la compilation du conteneur, avec le message de la bibliothèque. Les quêtes sont configurées ici ou
gardées en base de données (`catalog: doctrine`), mais pas les deux.

## Les quêtes qui reviennent

Avec `phil/almanac` installé, la clé `recurs` fait revenir une quête chaque année aux dates que calcule l'almanach. Ce
sont des jours entiers dans le fuseau horaire configuré :

```yaml
recurs: { from: 'easter - 2 days', until: 'easter + 1 day' }   # from Good Friday to Easter Monday
recurs: { from: '10-25', days: 7 }                              # a week from October 25
recurs: { from: 'winter solstice', days: 3 }
```

`from` est une règle écrite dans les mots de l'almanach. La quête dure `days` jours, un seul par défaut, ou jusqu'au
premier jour d'une autre règle avec `until`. Chaque occurrence porte le nom de sa date d'ouverture, comme `2027-03-26`,
et la quête s'accomplit une fois par occurrence.

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Les dépôts en mémoire,
pour les tests, se vident quand le noyau se réinitialise, et la projection oublie ce que sa dernière reconstruction
avait gardé pour la relecture.
