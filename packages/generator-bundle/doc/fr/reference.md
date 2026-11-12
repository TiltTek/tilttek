<!-- phil-dev source-sha256=d5265fbbde79e2196db5d27b1a90c109c07152ccccada9cbdaa2d1ca52c06994 -->
# Référence

| Type | Rôle |
|---|---|
| `TilttekGeneratorBundle` | Le bundle : sa configuration, et le répertoire des contrôleurs pour AssetMapper. |
| `Application\ApiAccess` | Qui peut tirer dans quel générateur de l'API, et contre quelle limite de débit. |
| `Application\Client` | Un client de l'API : son nom, son jeton, ses générateurs et sa limite de débit. |
| `Application\Grant` | Un tirage permis : qui le demande, sa clé, et la limite de débit contre laquelle il compte. |
| `Application\DrawRequest` | Lit un `Draw` dans les paramètres d'une requête, son état compris. |
| `Application\MemoryTables` | Tire à partir de l'état gardé sous une clé et garde le nouveau ; lit, fixe et oublie un état. |
| `Application\Contract\DrawRateLimiter` | Le port d'une limite de débit, par clé. |
| `Application\Contract\StateStore` | Le port du stockage des états, par générateur et par clé. |
| `Application\Contract\StateLock` | Le port du verrou qui sépare deux tirages sur le même état. |
| `Application\Exception\AccessDenied` | Un tirage que l'API refuse. |
| `Application\Exception\InvalidDrawRequest` | Des paramètres illisibles. |
| `Application\Exception\InvalidStateKey` | Une clé d'état vide ou trop longue. |
| `Infrastructure\Catalog\CachedCatalog` | Le catalogue des fichiers, mis en cache, rafraîchi en mode débogage. |
| `Infrastructure\Catalog\FileChanges` | Demande aux fichiers du catalogue s'ils ont changé, chaque fois que le cache est lu en mode débogage. |
| `Infrastructure\State\CacheStateStore` | Les états dans un pool de cache PSR-6, sans expiration. |
| `Infrastructure\State\InMemoryStateStore` | Les états en mémoire, pour les tests, réinitialisés avec le noyau. |
| `Infrastructure\Lock\SymfonyStateLock` | Le verrou d'un état sur la fabrique de verrous de Symfony, par une empreinte du générateur et de la clé, gardé 30 secondes au plus. |
| `Infrastructure\Catalog\GeneratorFactory` | Construit le générateur à partir d'un catalogue. |
| `Infrastructure\Http\DrawController` | L'API de tirage. |
| `Infrastructure\Console\ValidateCommand` | `tilttek:generator:validate`. |
| `Infrastructure\Console\CreateCommand` | `tilttek:generator:create`. |
| `Infrastructure\Console\DrawCommand` | `tilttek:generator:draw`. |
| `Infrastructure\Twig\DrawExtension` | `tilttek_draw()`. |
| `Infrastructure\RateLimit\SymfonyDrawRateLimiter` | La limite de débit d'un limiteur de débit de Symfony. |
| `Infrastructure\DependencyInjection\GeneratorServices` | Enregistre les services à partir de la configuration. |
| `Infrastructure\DependencyInjection\PublicRateLimiterPass` | Refuse le conteneur quand la limite de débit par défaut des tirages publics n'a pas de constructeur de limiteurs. |

Le gabarit `@TilttekGenerator/draw.html.twig`, le fichier de routes `config/routes.php` et le contrôleur Stimulus
`assets/src/generator_controller.js`, avec ses fonctions dans `assets/src/lib/draws.js`, complètent le bundle.
