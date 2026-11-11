# Reference

| Class | Role | Service |
|---|---|---|
| `TilttekQuestsBundle` | The bundle and its configuration `tilttek_quests` | — |
| `Infrastructure\DependencyInjection\QuestsServices` | Registers the services | — |
| `Infrastructure\Doctrine\QuestsTables` | The five tables | — |
| `Infrastructure\Doctrine\QuestsSchemaListener` | Adds them to the schema the ORM generates | listener of `postGenerateSchema` |
| `Infrastructure\Doctrine\Rows` | The insertion written once, the values of a row | — |
| `Infrastructure\Doctrine\DbalContributionStore` | `ContributionStore` on DBAL | alias `Tilttek\Quests\Store\ContributionStore` |
| `Infrastructure\Doctrine\DbalEnrolmentStore` | `EnrolmentStore` on DBAL | alias `Tilttek\Quests\Store\EnrolmentStore` |
| `Infrastructure\Doctrine\DbalCompletionStore` | `CompletionStore` on DBAL | alias `Tilttek\Quests\Store\CompletionStore` |
| `Infrastructure\Doctrine\DbalDuelStore` | `DuelStore` on DBAL | alias `Tilttek\Quests\Store\DuelStore` |
| `Infrastructure\Doctrine\DbalCatalog` | The quests in the database | alias `Tilttek\Quests\Catalog` and `WritableCatalog`, with `catalog: doctrine` |
| `Infrastructure\Almanac\AlmanacSchedule` | A quest that comes back at the almanac's dates | — |
| `Infrastructure\Almanac\AlmanacScheduleReader` | Reads `recurs` | given to `Tilttek\Quests\QuestReader` |
| `Infrastructure\Event\DispatchingObserver` | What the library tells, as events | — |
| `Infrastructure\Event\DuelWon`, `Infrastructure\Event\DuelExpired` | A duel won; a duel expired | events |
| `Infrastructure\Twig\QuestsExtension` | The Twig functions | Twig runtime |
| `Infrastructure\Console\ListCommand`, `ShowCommand`, `ExpireCommand` | The three commands | console commands |
| `Infrastructure\Privacy\QuestsPrivacy` | Export and erasure for `phil/privacy-bundle` | exporter and eraser |

The library's `Tilttek\Quests\Quests`, `Tilttek\Quests\Duel\Duels`, `Tilttek\Quests\QuestProjection` and
`Tilttek\Quests\Catalog` are public services.
