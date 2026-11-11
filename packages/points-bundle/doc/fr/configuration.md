<!-- phil-dev source-sha256=341f1a3fd83c562b2ff1c6a159115d7066186ebbbdd9c64134b8887496bbb0a0 -->
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

| Clé | Par défaut | Signification |
|---|---|---|
| `store` | `doctrine` | `doctrine` garde le registre en base de données ; `memory` le garde dans le processus, pour les tests |
| `connection` | `default` | La connexion DBAL, `doctrine.dbal.<name>_connection` ; l'écouteur de schéma y est lié |
| `currencies` | `{points: ~}` | Au moins une ; une durée de vie est une durée ISO 8601 (`P6M`, `P1Y`), écrite comme valeur ou sous la forme `{lifetime: P1Y}` |
| `default_currency` | `null` | Un code parmi les devises ; un code inconnu échoue à la compilation du conteneur |
| `timezone` | `UTC` | Un identifiant de fuseau horaire ; un mauvais identifiant échoue à la compilation du conteneur |
| `rules` | `{}` | Par nom ; `verbs` (au moins un) et `points` (au moins 1) sont requis ; une règle dans une devise inconnue, un mauvais nom, motif de verbe ou période échoue à la compilation du conteneur ; une condition de contexte est un entier, un booléen ou une chaîne |
| `projection` | `points` | Changez-la pour faire fonctionner deux systèmes de points côte à côte |
| `batch_size` | `500` | Au moins 1 |
| `privacy.member_kind` | `member` | Le type de la référence que représente une personne concernée de `phil/privacy-bundle` |

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Le registre en mémoire,
pour les tests, se vide quand le noyau est réinitialisé.
