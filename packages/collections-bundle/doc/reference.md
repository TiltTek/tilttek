# Reference

| Class | Role |
|---|---|
| `TilttekCollectionsBundle` | The bundle, its configuration and the privacy module |
| `Infrastructure\DependencyInjection\CollectionsServices` | The services, from the configuration |
| `Infrastructure\Doctrine\CollectionsTables` | The six tables |
| `Infrastructure\Doctrine\CollectionsSchemaListener` | The tables in the schema the ORM generates |
| `Infrastructure\Doctrine\Rows` | The idempotent insertion per platform, and the values of a row |
| `Infrastructure\Doctrine\DbalCopyStore`, `DbalCompletionStore`, `DbalOpeningStore`, `DbalChoiceStore`, `DbalDailyStore` | The library's stores on DBAL |
| `Infrastructure\Doctrine\DbalCatalog` | The catalog in the database |
| `Infrastructure\Almanac\AlmanacDateRule` | An item of the day on computed dates |
| `Infrastructure\Exchange\CollectionsInventory` | The inventory of `tilttek/exchange` |
| `Infrastructure\Dice\CollectionDiceAppearance` | The dice appearance of `tilttek/dice-bundle` |
| `Infrastructure\Event\DispatchingObserver`, `CopyGranted` | The events |
| `Infrastructure\Console\DailyCommand`, `GrantCommand`, `AlbumCommand` | The commands |
| `Infrastructure\Twig\CollectionsExtension` | The Twig functions |
| `Infrastructure\Privacy\CollectionsPrivacy` | The module of `phil/privacy-bundle` |
