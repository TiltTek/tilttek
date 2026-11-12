# Reference

| Type | Role |
|---|---|
| `TilttekGeneratorBundle` | The bundle: its configuration, and the controllers' directory for AssetMapper. |
| `Application\ApiAccess` | Who may draw which generator from the API, and against which rate limit. |
| `Application\Client` | A client of the API: its name, token, generators and rate limit. |
| `Application\Grant` | A draw allowed: who asks, its key, and the rate limit it counts against. |
| `Application\DrawRequest` | Reads a `Draw` from the parameters of a request, its state included. |
| `Application\MemoryTables` | Draws from the state kept under a key and keeps the new one; reads, sets and forgets a state. |
| `Application\Contract\DrawRateLimiter` | The port of a rate limit, by key. |
| `Application\Contract\StateStore` | The port of the store of the states, by generator and key. |
| `Application\Contract\StateLock` | The port of the lock that keeps two draws on the same state apart. |
| `Application\Exception\AccessDenied` | A draw the API refuses. |
| `Application\Exception\InvalidDrawRequest` | Parameters it cannot read. |
| `Application\Exception\InvalidStateKey` | A key of a state that is empty or too long. |
| `Infrastructure\Catalog\CachedCatalog` | The catalog of the files, cached, refreshed in debug. |
| `Infrastructure\Catalog\FileChanges` | Asks the files of the catalog whether they changed, each time the cache is read in debug. |
| `Infrastructure\State\CacheStateStore` | The states in a PSR-6 cache pool, without expiry. |
| `Infrastructure\State\InMemoryStateStore` | The states in memory, for tests, reset with the kernel. |
| `Infrastructure\Lock\SymfonyStateLock` | The lock of a state on Symfony's lock factory, by a hash of the generator and the key, kept 30 seconds at most. |
| `Infrastructure\Catalog\GeneratorFactory` | Builds the generator from a catalog. |
| `Infrastructure\Http\DrawController` | The draw API. |
| `Infrastructure\Console\ValidateCommand` | `tilttek:generator:validate`. |
| `Infrastructure\Console\CreateCommand` | `tilttek:generator:create`. |
| `Infrastructure\Console\DrawCommand` | `tilttek:generator:draw`. |
| `Infrastructure\Twig\DrawExtension` | `tilttek_draw()`. |
| `Infrastructure\RateLimit\SymfonyDrawRateLimiter` | The rate limit of a Symfony rate limiter. |
| `Infrastructure\DependencyInjection\GeneratorServices` | Registers the services from the configuration. |
| `Infrastructure\DependencyInjection\PublicRateLimiterPass` | Refuses the container when the default rate limit of the public draws has no rate limiter builder. |

The template `@TilttekGenerator/draw.html.twig`, the route file `config/routes.php` and the Stimulus controller
`assets/src/generator_controller.js` with its functions in `assets/src/lib/draws.js` complete the bundle.
