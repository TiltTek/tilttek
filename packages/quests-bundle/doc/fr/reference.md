<!-- phil-dev source-sha256=930378fd4f53b917ab3979e4cabf95451755fca64dbf90e073e4ca7b74894e6b -->
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

Le `Tilttek\Quests\NoTeamMembership` de la bibliothèque est enregistré comme le `Tilttek\Quests\Contract\TeamMembership`
que le bundle donne à `Quests`, jusqu'à ce que l'application fasse de cette interface un alias de son propre service.

Les classes `Tilttek\Quests\Quests`, `Tilttek\Quests\Duel\Duels`, `Tilttek\Quests\QuestProjection` et
`Tilttek\Quests\Catalog` de la bibliothèque sont des services publics.
