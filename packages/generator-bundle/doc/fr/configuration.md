<!-- phil-dev source-sha256=ee01fb5f1ba67a1ab4b4640c66c2835029ace0bfdfe55ee309f2b47a5f081c85 -->
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
        steps: 10000
        bytes: 65536
        megabytes: 16

    # The directory of Twig's compiled templates, loaded from their files rather than by eval().
    cache: '%kernel.cache_dir%/tilttek_generator/twig'

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

| Clé | Par défaut | Signification |
|---|---|---|
| `paths` | `null` | Les fichiers et les répertoires lus ; `null` lit `config/generators/` du projet quand il existe, et rien sinon. Un chemin configuré qui n'existe pas est une erreur à la première utilisation. |
| `locale` | `en` | La langue par défaut des filtres de grammaire. |
| `aspirated` | `[]` | Des mots ajoutés à la liste des h aspirés de la grammaire française. |
| `stressed_a` | `[]` | Des mots ajoutés à la liste de la grammaire espagnole des mots féminins dont la première syllabe est un a accentué (`el ánima`, `un ánima`), comparés sans tenir compte de la casse. |
| `limits.*` | comme montré | Les `Limits` de `tilttek/generator`, chacune d'au moins 1. |
| `cache` | `%kernel.cache_dir%/tilttek_generator/twig` | Le répertoire où Twig écrit les gabarits compilés des générateurs, qu'il charge ensuite de leurs fichiers, jamais par `eval()` ; `true` nomme la valeur par défaut. `false` et un texte vide sont refusés : il y a toujours un répertoire. |
| `tables` | `false` | Si les tables aléatoires de `tables/` de la bibliothèque sont lues après les chemins ; une liste ou un générateur de l'application nommé comme l'une d'elles est alors une erreur. |
| `tables_locale` | `en` | La langue des tables aléatoires, `en` ou `fr` ; les deux versions ont les mêmes noms, les mêmes valeurs d'état et les mêmes champs. |
| `state.store` | `cache` | Où sont gardés les états des tables à mémoire : `cache`, dans le pool ci-dessous, quand `psr/cache` est installé ; `memory`, pour les tests, réinitialisé avec le noyau ; ou le service du `StateStore` propre à l'application. |
| `state.pool` | `cache.app` | Le pool PSR-6 du stockage `cache`. `cache.app` oublie les états quand le cache est vidé ; un pool à part sur Doctrine DBAL ou Valkey les garde d'un déploiement à l'autre. |
| `state.lock` | `null` | Le service d'une fabrique de verrous de `symfony/lock`, comme `lock.factory`, qui tient chaque tirage sur un état du chargement à l'enregistrement ([Tables à mémoire](memory.md#le-verrou)) ; aucun verrou quand la valeur est `null`. |
| `api.enabled` | `false` | Si l'API de tirage répond ; 404 sinon. |
| `api.max_count` | `20` | Le plus de résultats qu'une requête peut demander, jamais plus que `limits.results`. |
| `api.public` | `[]` | Les générateurs tirés sans jeton, comptés par adresse de l'appelant. |
| `api.rate_limiter` | `null` | Le service d'une fabrique de limiteurs de débit pour les tirages publics ; celle du bundle, selon `api.public_limit`, quand la valeur est `null`. |
| `api.public_limit.limit`, `api.public_limit.interval` | `60`, `1 minute` | La limite de débit par défaut des tirages publics, par adresse, sur une fenêtre glissante. |
| `api.clients.<name>.token` | requis | Le jeton du client, comparé en temps constant ; donnez-le par une variable d'environnement ou un secret. |
| `api.clients.<name>.generators` | requis | Les générateurs ouverts au client, au moins un ; `*` les ouvre tous. |
| `api.clients.<name>.rate_limiter` | `null` | Le service de la fabrique de limiteurs de débit du client, compté par client. |

Une API qui ouvre des générateurs à tout le monde est limitée par défaut : sans `api.rate_limiter`, le bundle fait un
limiteur selon `api.public_limit` avec le constructeur de limiteurs de `symfony/rate-limiter`, soixante tirages par
minute et par adresse. Sans ce composant, ou avec `framework.rate_limiter` désactivé, le conteneur est refusé, avec ce
qu'il faut faire. Le temps d'un tirage entier, `limits.draw_milliseconds`, borne en plus ce qu'une requête peut coûter.

Les limiteurs de débit sont ceux de `framework.rate_limiter` ; un limiteur nommé `generator_public` est le service
`limiter.generator_public` :

```yaml
framework:
    rate_limiter:
        generator_public: { policy: sliding_window, limit: 60, interval: '1 minute' }
        generator_partner: { policy: token_bucket, limit: 600, rate: { interval: '1 minute', amount: 600 } }
```

Un pool qui garde les états d'un déploiement à l'autre, sur la connexion Doctrine de l'application :

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

Le catalogue est mis en cache dans `%kernel.cache_dir%/tilttek_generator/catalog.php`. En mode débogage, il est réécrit
quand un fichier ou un répertoire des chemins change, même dans la seconde où le cache a été écrit. Hors du mode
débogage, videz le cache après avoir modifié les fichiers.

D'une requête à l'autre, le générateur ne garde que les entrées de chaque liste filtrées par étiquette, au plus 64
filtres par liste, avec ce qu'une liste de Markov en a appris, et rien d'un tirage : il est sûr dans un *worker*. Les
états vivent dans leur stockage, pas dans un service.
