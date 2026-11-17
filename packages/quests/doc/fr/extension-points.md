<!-- phil-dev source-sha256=ec0b4121bf30d564a290715d6214b21354d6cad97e086a18457e2cd3a007e9df -->
# Points d'extension

| Point | Ce que donne une application | Où |
|---|---|---|
| `Catalog`, `WritableCatalog` | Les quêtes, à partir d'une configuration ou d'un catalogue que le site modifie | [Installation](installation.md) |
| `Store\ContributionStore`, `Store\CompletionStore`, `Store\EnrolmentStore`, `Store\DuelStore` | Le stockage des contributions, des accomplissements, des inscriptions et des duels, à la place des magasins en mémoire | [Écrire un magasin](writing-a-store.md) |
| `Schedule\Schedule`, `Schedule\ScheduleReader` | Quand une quête est ouverte au-delà des calendriers intégrés, lu dans les clés de sa définition | [Calendriers et enchaînements](schedules-and-chains.md#quand-une-quête-est-ouverte) |
| `Contract\TeamMembership` | Si un membre fait partie de l'équipe sous laquelle il rejoint une quête d'équipe ; sans lui, aucun membre ne fait partie d'aucune équipe | [Suivre une quête](following.md#appartenance-à-léquipe) |
| `QuestObserver` | Ce qui est fait quand une quête est accomplie, qu'un duel est gagné ou qu'un duel expire | [La projection](projection.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise ; les services ne gardent aucun état propre entre deux
appels.
