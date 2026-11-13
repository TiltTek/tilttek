<!-- phil-dev source-sha256=30022c452ee233fe51fc0956805865b7f2a1ca8961ecad10dbcc6563b9a5c32e -->
# Référence

| Classe ou fichier | Rôle |
|---|---|
| `TilttekGameEngineBundle` | Le bundle et sa configuration `tilttek_game_engine` |
| `Application\GameTables` | Les tables de l'application : ouvertes, jouées, reprises et lues, sous le verrou de chaque table, et le journal d'une table terminée |
| `Application\Viewer` | Qui lit une table : un siège avec ses préférences, le public, ou personne |
| `Application\Contract\ViewerResolver` | La réponse de l'application à la question de qui lit une table |
| `Application\AudienceViewerResolver` | Le lecteur quand l'application n'en nomme aucun : le public, ou personne |
| `Application\Contract\TableLock` | Sépare deux coups à une même table |
| `Application\SeatIdentifiers` | Refuse un identifiant de siège qui ressemble à une adresse courriel ou à une référence à un membre |
| `Application\Exception\IdentifyingSeat` | Un identifiant de siège refusé parce qu'il nomme une personne |
| `Infrastructure\Doctrine\DbalLogStore` | Les journaux sur Doctrine DBAL : en-têtes, coups lus fenêtre par fenêtre, changements comparés à la révision lue |
| `Infrastructure\Doctrine\GameEngineTables` | Les deux tables des journaux, ajoutées à un schéma |
| `Infrastructure\Doctrine\GameEngineSchemaListener` | Les tables dans le schéma que l'ORM génère |
| `Infrastructure\Cache\CacheStateCache` | Les états dans un pool PSR-6, sous forme de texte JSON, avec la révision de leur journal, écrits sous le verrou de leur clé quand une fabrique de verrous est donnée |
| `Infrastructure\Lock\SymfonyTableLock` | Le verrou d'une table sur la fabrique de verrous de Symfony |
| `Infrastructure\Mercure\MercureTablePublisher` | Chaque changement sur le sujet Mercure de sa table, avec des identifiants seulement |
| `Infrastructure\Mercure\TableTopic` | Le sujet de chaque table, à partir de son modèle |
| `Infrastructure\Http\TableController` | Les points de terminaison de la vue d'un siège et du journal d'une table terminée |
| `Infrastructure\Console\SetupCommand` | `tilttek:game-engine:setup` |
| `Infrastructure\Console\VerifyCommand` | `tilttek:game-engine:verify` |
| `Infrastructure\Twig\GameEngineExtension` | `tilttek_game_table()`, `tilttek_game_engine_kit()`, `tilttek_game_engine_domain()` |
| `Infrastructure\Twig\KitComponents` | Les noms sous lesquels les recettes du kit sont installées |
| `Infrastructure\DependencyInjection\GameEngineServices` | Les services du bundle, selon la configuration et les composants installés |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Le sous-répertoire du kit, à partir du Toolkit de Symfony UX |
| `config/routes.php` | Les routes `tilttek_game_engine_view` et `tilttek_game_engine_log` |
| `templates/table.html.twig` | Une table comme tous ceux qui y sont peuvent la voir, et l'endroit de la vue |
| `assets/src/game_table_controller.js` | Le contrôleur Stimulus `game-table` |
| `translations/tilttek_game_engine.*.yaml` | Les textes du gabarit, en anglais et en français |
| `recipe/0.1/` | La recette Flex : le bundle enregistré, sa configuration et ses routes écrites |
