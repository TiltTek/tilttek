# Configuration

```yaml
tilttek_generator:
    # Files and directories of YAML and JSON documents; config/generators of the project when it exists and no path
    # is given.
    paths: ['%kernel.project_dir%/config/generators']

    # The language of the generators that do not name theirs.
    locale: en

    # More French words whose h is aspirated, which keep their article whole.
    aspirated: [hobgobelin]

    # More Spanish feminine words whose first syllable is a stressed a, which take el and un.
    stressed_a: [ánima]

    # The bounds that stop a generator gone wild.
    limits:
        depth: 8
        picks: 1000
        milliseconds: 250
        attempts: 10
        results: 100
        cells: 2500
        draw_milliseconds: 1000

    # Keeps Twig's compiled templates in %kernel.cache_dir%/tilttek_generator/twig.
    cache: true

    # Loads the library's random tables too, named table.<name>.
    tables: false

    # The language of the random tables: "en" or "fr".
    tables_locale: en

    # Where the tables with a memory keep their states between draws.
    state:
        # "cache", "memory" for tests, or the service of a store of the application.
        store: cache
        # The PSR-6 cache pool of the "cache" store.
        pool: cache.app
        # The service of a Symfony lock factory that keeps two draws on the same state apart; none by default.
        lock: lock.factory

    # The draw API: off by default; only the generators it names are drawn, never their lists.
    api:
        enabled: false
        # The most results of one draw, within limits.results.
        max_count: 20
        # The generators anyone may draw, without a token.
        public: [name.short]
        # The rate limiter factory service of the public draws, counted by address; the bundle's own when null.
        rate_limiter: limiter.generator_public
        # The default rate limit of the public draws, by address, when no rate limiter is named.
        public_limit: { limit: 60, interval: '1 minute' }
        # The sites and applications that draw with a token, by name.
        clients:
            partner:
                token: '%env(GENERATOR_PARTNER_TOKEN)%'
                # The generators open to the client; "*" opens them all.
                generators: [tavern, name.short]
                rate_limiter: limiter.generator_partner
```

| Key | Default | Meaning |
|---|---|---|
| `paths` | `null` | The files and directories read; `null` reads `config/generators/` of the project when it exists, and nothing otherwise. A configured path that does not exist is an error at the first use. |
| `locale` | `en` | The default language of the grammar filters. |
| `aspirated` | `[]` | Words added to the French grammar's list of aspirated h. |
| `stressed_a` | `[]` | Words added to the Spanish grammar's list of feminine words whose first syllable is a stressed a (`el ánima`, `un ánima`), compared without regard to case. |
| `limits.*` | as shown | The `Limits` of `tilttek/generator`, each at least 1. |
| `cache` | `true` | Whether Twig keeps its compiled templates in the cache directory. |
| `tables` | `false` | Whether the library's random tables of `tables/` are read after the paths; an application's list or generator named like one of them is then an error. |
| `tables_locale` | `en` | The language of the random tables, `en` or `fr`; both versions have the same names, state values and fields. |
| `state.store` | `cache` | Where the states of the tables with a memory are kept: `cache`, in the pool below, when `psr/cache` is installed; `memory`, for tests, reset with the kernel; or the service of the application's own `StateStore`. |
| `state.pool` | `cache.app` | The PSR-6 pool of the `cache` store. `cache.app` forgets the states when the cache is cleared; a pool of its own on Doctrine DBAL or Valkey keeps them across deployments. |
| `state.lock` | `null` | The service of a lock factory of `symfony/lock`, such as `lock.factory`, that holds each draw on a state from its load to its save ([Tables with a memory](memory.md#the-lock)); no lock when `null`. |
| `api.enabled` | `false` | Whether the draw API answers; 404 otherwise. |
| `api.max_count` | `20` | The most results one request may ask for, never more than `limits.results`. |
| `api.public` | `[]` | The generators drawn without a token, counted by the caller's address. |
| `api.rate_limiter` | `null` | The service of a rate limiter factory for the public draws; the bundle's own, by `api.public_limit`, when `null`. |
| `api.public_limit.limit`, `api.public_limit.interval` | `60`, `1 minute` | The default rate limit of the public draws, by address, on a sliding window. |
| `api.clients.<name>.token` | required | The client's token, compared in constant time; give it from an environment variable or a secret. |
| `api.clients.<name>.generators` | required | The generators open to the client, at least one; `*` opens them all. |
| `api.clients.<name>.rate_limiter` | `null` | The service of the client's rate limiter factory, counted by client. |

An API that opens generators to anyone is rate limited by default: without `api.rate_limiter`, the bundle makes a
limiter of `api.public_limit` with the rate limiter builder of `symfony/rate-limiter`, sixty draws a minute by address.
Without that component, or with `framework.rate_limiter` disabled, the container is refused with what to do. The time
of a whole draw, `limits.draw_milliseconds`, bounds what one request may cost on top of it.

The rate limiters are those of `framework.rate_limiter`; a limiter named `generator_public` is the service
`limiter.generator_public`:

```yaml
framework:
    rate_limiter:
        generator_public: { policy: sliding_window, limit: 60, interval: '1 minute' }
        generator_partner: { policy: token_bucket, limit: 600, rate: { interval: '1 minute', amount: 600 } }
```

A pool that keeps the states across deployments, on the application's Doctrine connection:

```yaml
framework:
    cache:
        pools:
            cache.generator_states:
                adapter: cache.adapter.doctrine_dbal
                default_lifetime: 0

tilttek_generator:
    state: { pool: cache.generator_states }
```

The catalog is cached in `%kernel.cache_dir%/tilttek_generator/catalog.php`; in debug, it is written again when a file
or a directory of the paths changes, even in the second the cache was written. Out of debug, clear the cache after
changing the files.

The generator keeps between requests only the entries of each list filtered by tag, at most 64 filters by list, with
what a Markov list learnt from them, and nothing of a draw: it is safe in a worker. The states live in their store, not
in a service.
