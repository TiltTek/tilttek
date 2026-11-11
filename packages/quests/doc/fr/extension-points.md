<!-- phil-dev source-sha256=d23ce362b4ce1bd9df1504f648596a475444b508d3565a9fcd1963e77e04d75c -->
# Points d'extension

| Point | Ce que donne une application | Où |
|---|---|---|
| `Catalog`, `WritableCatalog` | Les quêtes, à partir d'une configuration ou d'un catalogue que le site modifie | [Installation](installation.md) |
| `Store\ContributionStore`, `Store\CompletionStore`, `Store\EnrolmentStore`, `Store\DuelStore` | Le stockage des contributions, des accomplissements, des inscriptions et des duels, à la place des magasins en mémoire | [Écrire un magasin](writing-a-store.md) |
| `Schedule\Schedule`, `Schedule\ScheduleReader` | Quand une quête est ouverte au-delà des calendriers intégrés, lu dans les clés de sa définition | [Calendriers et enchaînements](schedules-and-chains.md#quand-une-quête-est-ouverte) |
| `QuestObserver` | Ce qui est fait quand une quête est accomplie, qu'un duel est gagné ou qu'un duel expire | [La projection](projection.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise ; les services ne gardent aucun état propre entre deux
appels.
