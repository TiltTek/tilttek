<!-- phil-dev source-sha256=462dd109e94337bf842c596b28dde30d80ece5e657be7f2a8ac9a86d02635502 -->
# Configuration

```yaml
tilttek_exchange:
    store: doctrine                # or memory, for tests
    connection: default            # the DBAL connection of the tables
    catalog: configuration         # or doctrine: the table tilttek_exchange_reward
    rewards:
        discount-10: { price: 100 }
        seat:
            price: 250
            currency: stars        # the default currency of tilttek/points when null
            stock: 30              # every unit the reward ever has; unlimited when null
            available_from: '2026-11-01'
            available_until: '2026-12-01'
            per_member: 1
            requires: ['badge:helper']
    reservation_lifetime: PT30M    # ISO 8601
    requirements: ~                # service implementing Tilttek\Exchange\Reward\Requirements
    member_since: ~                # service implementing Tilttek\Exchange\MemberSince
    timezone: UTC                  # where the periods of the allowances are counted
    transfers:
        enabled: false
        allowance: { limit: 500, period: week }   # day, week, month, year or ever; none when left out
        new_member_delay: ~        # ISO 8601, like P7D
    trades:
        enabled: false
        inventory: ~               # service implementing Tilttek\Exchange\Trade\Inventory
        offer_lifetime: P7D
        allowance: { limit: 10, period: day }
        new_member_delay: ~
    privacy:
        member_kind: member        # a data subject 44 is the member member:44
```

| Clé | Par défaut | Signification |
|---|---|---|
| `store` | `doctrine` | Où les réservations, les transferts et les offres sont gardés : `doctrine`, ou `memory` pour les tests. |
| `connection` | `default` | La connexion DBAL des tables. |
| `catalog` | `configuration` | Les récompenses viennent de `rewards`, ou de la table `tilttek_exchange_reward` avec `doctrine`, qui exige le magasin `doctrine` et un `rewards` vide. |
| `rewards` | aucune | Les récompenses par code (le code est gardé tel qu'écrit, traits d'union compris), comme les décrit [Récompenses](rewards.md). |
| `reservation_lifetime` | `PT30M` | Combien de temps une réservation attend sa confirmation. |
| `requirements` | aucun | Le service qui vérifie les conditions des récompenses ; sans lui, aucune condition n'est remplie. |
| `member_since` | aucun | Le service qui dit quand un membre s'est inscrit ; exigé par un `new_member_delay`. |
| `timezone` | `UTC` | Le fuseau horaire des périodes des allocations. |
| `transfers.enabled` | `false` | Si les membres se donnent des points ; `Transfers` et la commande du journal n'existent que dans ce cas. |
| `trades.enabled` | `false` | Si les membres échangent des objets ; `Trades` n'existe que dans ce cas, et exige `trades.inventory`. |
| `*.allowance` | aucune | Le maximum qu'un membre donne sur une période : des points pour les transferts, des offres pour les échanges. |
| `*.new_member_delay` | aucun | Combien de temps un compte attend avant de faire des échanges avec les autres. |
| `trades.offer_lifetime` | `P7D` | Combien de temps une offre attend sa réponse. |
| `privacy.member_kind` | `member` | Le type de la référence de membre que représente une personne concernée. |

À la compilation du conteneur, `InvalidExchange` refuse une récompense incohérente, une durée ou un fuseau horaire qui
n'existe pas, un catalogue en base de données sans le magasin `doctrine`, un délai sans `member_since` et des échanges
sans inventaire.

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Les magasins en mémoire,
pour les tests, se vident quand le noyau est réinitialisé.
