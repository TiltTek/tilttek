<!-- phil-dev source-sha256=bcc0ae47bab9a19388af3435ba013d914c9f553e35e902e70f90203d5a434b68 -->
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

Chaque badge, par code, prend `name`, `description`, `image`, `repeatable`, `hidden`, `revocable`, `tier` (`series`,
`rank`) et son `criterion`, l'un de ceux-ci :

| Critère | Clés |
|---|---|
| `count` | `verbs`, `times`, et `context`, `since`, `until` |
| `first` | `verbs`, et `context`, `since`, `until` |
| `total` | `verbs`, `amount`, et `context`, `since`, `until` |
| `streak` | `verbs`, `days`, et `context`, `since`, `until` |
| `measure` | `name`, `threshold`, `verbs` — lu par le service `Tilttek\Badges\Measures` de l'application |
| `manual` | `true` |
| `all` | une liste de critères |
| `at_least` | `count`, et `of`, une liste de critères |

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

Le catalogue est lu à la compilation du conteneur : une erreur fait échouer la construction avec l'`InvalidBadge` de la
bibliothèque.

## Des badges dans la base de données

Le catalogue est le service `Tilttek\Badges\Catalog`, construit à partir de cette configuration. Une application qui
modifie ses badges dans la base de données remplace ce service par sa propre fabrique, qui renvoie un `Catalog` :
`Catalog::fromArray()` lit les mêmes valeurs simples à partir de lignes.

Les services ne gardent aucun état entre les requêtes : ils sont sûrs dans un *worker*. Les stockages en mémoire, pour
les tests, se vident quand le noyau se réinitialise, et la projection oublie ce que sa dernière reconstruction gardait
pour la relecture.
