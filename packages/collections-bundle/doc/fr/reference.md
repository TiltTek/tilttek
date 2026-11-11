<!-- phil-dev source-sha256=5e572b9e420ab0323fce7925d1726db21fcb16ed61f2d184d293ba3f2652fe6b -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekCollectionsBundle` | Le bundle, sa configuration et le module de vie privée |
| `Infrastructure\DependencyInjection\CollectionsServices` | Les services, à partir de la configuration |
| `Infrastructure\Doctrine\CollectionsTables` | Les six tables |
| `Infrastructure\Doctrine\CollectionsSchemaListener` | Les tables dans le schéma que génère l'ORM |
| `Infrastructure\Doctrine\Rows` | L'insertion idempotente selon la plateforme, et les valeurs d'une ligne |
| `Infrastructure\Doctrine\DbalCopyStore`, `DbalCompletionStore`, `DbalOpeningStore`, `DbalChoiceStore`, `DbalDailyStore` | Les stockages de la bibliothèque sur DBAL |
| `Infrastructure\Doctrine\DbalCatalog` | Le catalogue dans la base de données |
| `Infrastructure\Almanac\AlmanacDateRule` | Un objet du jour à des dates calculées |
| `Infrastructure\Exchange\CollectionsInventory` | L'inventaire de `tilttek/exchange` |
| `Infrastructure\Dice\CollectionDiceAppearance` | L'apparence des dés de `tilttek/dice-bundle` |
| `Infrastructure\Event\DispatchingObserver`, `CopyGranted` | Les événements |
| `Infrastructure\Console\DailyCommand`, `GrantCommand`, `AlbumCommand` | Les commandes |
| `Infrastructure\Twig\CollectionsExtension` | Les fonctions Twig |
| `Infrastructure\Privacy\CollectionsPrivacy` | Le module de `phil/privacy-bundle` |
