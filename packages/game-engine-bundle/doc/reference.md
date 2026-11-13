# Reference

| Class or file | Role |
|---|---|
| `TilttekGameEngineBundle` | The bundle and its configuration `tilttek_game_engine` |
| `Application\GameTables` | The tables of the application: opened, played, taken back and read, under the lock of each table, and the log of a finished table |
| `Application\Viewer` | Who reads a table: a seat with its preferences, the audience, or no one |
| `Application\Contract\ViewerResolver` | The application's answer to who reads a table |
| `Application\AudienceViewerResolver` | The viewer when the application names none: the audience, or no one |
| `Application\Contract\TableLock` | Keeps two moves at one table apart |
| `Application\SeatIdentifiers` | Refuses a seat identifier that looks like an email address or a reference to a member |
| `Application\Exception\IdentifyingSeat` | A seat identifier refused for naming a person |
| `Infrastructure\Doctrine\DbalLogStore` | The logs on Doctrine DBAL: headers, moves read window by window, changes compared with the revision read |
| `Infrastructure\Doctrine\GameEngineTables` | The two tables of the logs, added to a schema |
| `Infrastructure\Doctrine\GameEngineSchemaListener` | The tables in the schema the ORM generates |
| `Infrastructure\Cache\CacheStateCache` | The states in a PSR-6 pool, as JSON text, with the revision of their log, written under the lock of their key when a lock factory is given |
| `Infrastructure\Lock\SymfonyTableLock` | The lock of a table on Symfony's lock factory |
| `Infrastructure\Mercure\MercureTablePublisher` | Every change on the Mercure topic of its table, with identifiers only |
| `Infrastructure\Mercure\TableTopic` | The topic of each table, from its template |
| `Infrastructure\Http\TableController` | The endpoints of the view of a seat and of the log of a finished table |
| `Infrastructure\Console\SetupCommand` | `tilttek:game-engine:setup` |
| `Infrastructure\Console\VerifyCommand` | `tilttek:game-engine:verify` |
| `Infrastructure\Twig\GameEngineExtension` | `tilttek_game_table()`, `tilttek_game_engine_kit()`, `tilttek_game_engine_domain()` |
| `Infrastructure\Twig\KitComponents` | The names the recipes of the kit are installed under |
| `Infrastructure\DependencyInjection\GameEngineServices` | The bundle's services, by configuration and by installed component |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | The subdirectory of the kit, from the Symfony UX Toolkit |
| `config/routes.php` | The routes `tilttek_game_engine_view` and `tilttek_game_engine_log` |
| `templates/table.html.twig` | A table as anyone at it may see it, and the place of the view |
| `assets/src/game_table_controller.js` | The Stimulus controller `game-table` |
| `translations/tilttek_game_engine.*.yaml` | The template's texts, in English and French |
| `recipe/0.1/` | The Flex recipe: the bundle registered, its configuration and its routes written |
