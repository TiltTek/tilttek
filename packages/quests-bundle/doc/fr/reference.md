<!-- phil-dev source-sha256=c7e84c6da20cf01fe96b6ac9e3315960b5d87e6f7d806a1703f6be55783be35a -->
# Référence

| Classe | Rôle | Service |
|---|---|---|
| `TilttekQuestsBundle` | Le bundle et sa configuration `tilttek_quests` | — |
| `Infrastructure\DependencyInjection\QuestsServices` | Enregistre les services | — |
| `Infrastructure\Doctrine\QuestsTables` | Les cinq tables | — |
| `Infrastructure\Doctrine\QuestsSchemaListener` | Les ajoute au schéma généré par l'ORM | écouteur de `postGenerateSchema` |
| `Infrastructure\Doctrine\Rows` | L'insertion écrite une seule fois, les valeurs d'une ligne | — |
| `Infrastructure\Doctrine\DbalContributionStore` | `ContributionStore` sur DBAL | alias `Tilttek\Quests\Store\ContributionStore` |
| `Infrastructure\Doctrine\DbalEnrolmentStore` | `EnrolmentStore` sur DBAL | alias `Tilttek\Quests\Store\EnrolmentStore` |
| `Infrastructure\Doctrine\DbalCompletionStore` | `CompletionStore` sur DBAL | alias `Tilttek\Quests\Store\CompletionStore` |
| `Infrastructure\Doctrine\DbalDuelStore` | `DuelStore` sur DBAL | alias `Tilttek\Quests\Store\DuelStore` |
| `Infrastructure\Doctrine\DbalCatalog` | Les quêtes en base de données | alias `Tilttek\Quests\Catalog` et `WritableCatalog`, avec `catalog: doctrine` |
| `Infrastructure\Almanac\AlmanacSchedule` | Une quête qui revient aux dates de l'almanach | — |
| `Infrastructure\Almanac\AlmanacScheduleReader` | Lit `recurs` | fourni à `Tilttek\Quests\QuestReader` |
| `Infrastructure\Event\DispatchingObserver` | Ce que la bibliothèque annonce, sous forme d'événements | — |
| `Infrastructure\Event\DuelWon`, `Infrastructure\Event\DuelExpired` | Un duel gagné ; un duel expiré | événements |
| `Infrastructure\Twig\QuestsExtension` | Les fonctions Twig | runtime Twig |
| `Infrastructure\Console\ListCommand`, `ShowCommand`, `ExpireCommand` | Les trois commandes | commandes de console |
| `Infrastructure\Privacy\QuestsPrivacy` | Exportation et effacement pour `phil/privacy-bundle` | exportateur et effaceur |

Les classes `Tilttek\Quests\Quests`, `Tilttek\Quests\Duel\Duels`, `Tilttek\Quests\QuestProjection` et
`Tilttek\Quests\Catalog` de la bibliothèque sont des services publics.
