# tilttek/generator-bundle

**Random generators for Symfony: the application's lists and templates, drawn in its pages, its terminal and its API.**

tilttek/generator-bundle integrates `tilttek/generator` with a Symfony application. It reads the lists and the
generators from the YAML and JSON files of the application, keeps them in the cache, and builds the generator that
checks them on first use, whose templates Twig compiles into files of the cache directory and loads from there, never
by `eval()`. It adds commands that validate the files, write a new generator and draw in the terminal, a
Twig function that draws in a template, a template and a Stimulus controller that draw again in place, and a draw API
that other sites and applications call without ever receiving the lists. It loads the library's random tables when
asked, in English or in French, and keeps the states of the tables with a memory — the weather of a campaign, a journey,
an exploration — by generator and key, in a cache pool or in a store of the application. The API is off until the
application enables it, and opens only the generators it names, to anyone or to the clients that show their token, under
the rate limits of `framework.rate_limiter`.

It is for Symfony applications that offer generators to their members or to other sites: a name generator page, a game
master's toolbox, random tables served to a virtual table. The lists belong to the application; the bundle ships none,
and the library's random tables are loaded only when the application asks for them.

It is a Symfony bundle (`AbstractBundle`) on `tilttek/generator` and `tilttek/dice`. Its integrations — the API, the
commands, the rate limits, the cache of the states, the Twig function and the Stimulus controller — are registered only
when their component is installed. When `tilttek/dice-bundle` is installed, its dice, custom ones included, roll in the
templates.

The design is small: `CachedCatalog` reads and caches the files, `GeneratorFactory` builds the `Generator`,
`MemoryTables` draws from the state a `StateStore` keeps, behind a `StateLock` when one is configured, `ApiAccess`
decides who may draw what, `DrawController` serves the draws, and the commands and the Twig extension use the same
generator.

## Status

| Part | State |
|---|---|
| Lists and generators read from files and directories, cached, refreshed in debug | Implemented and tested |
| The library's random tables, loaded when asked, in English or in French | Implemented and tested |
| Tables with a memory: states kept by generator and key in a cache pool, in memory or in the application's store | Implemented and tested |
| An optional lock on `symfony/lock`, so that two draws on the same state do not lose an update | Implemented and tested |
| Commands: validate, create — a text, fields, a map, a table with a memory, or a copy of a random table — and draw, with a key | Implemented and tested |
| Twig function `tilttek_draw`, template and Stimulus controller that draw again, with the attributions of the results | Implemented and tested |
| Compiled templates in files of the cache directory, never loaded by `eval()` | Implemented and tested |
| Draw API: off by default, public generators by address, clients by token, rate limits, problem details, states sent back by the client, attributions | Implemented and tested |
| The dice of `tilttek/dice-bundle` in the templates, when it is installed | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the files and the routes. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_generator`. |
| [Commands](doc/commands.md) | `tilttek:generator:validate`, `create` and `draw`. |
| [Tables with a memory](doc/memory.md) | `MemoryTables`, the store of the states, the lock, and the states through the API. |
| [Templates](doc/templates.md) | `tilttek_draw`, the template that draws again and its Stimulus controller. |
| [Draw API](doc/api.md) | The route, the parameters, the answers, the access and the rate limits. |
| [Extension points](doc/extension-points.md) | The services an application may replace or use. |
| [Exceptions](doc/exceptions.md) | The exceptions of the bundle and how the API answers them. |
| [Testing](doc/testing.md) | The bundle's tests and mutation bar, and how to test an application's generators. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

The generators' syntax — lists, templates, grammar, maps and draws — is that of `tilttek/generator`, documented in its
own `doc/`.

## Quick start

```sh
composer require tilttek/generator-bundle
```

```yaml
# config/generators/names.yaml
lists:
  name.syllable: [ka, ri, to, mu, sel]
generators:
  name.short:
    templates: "{% set a = pick('name.syllable') %}{{ a }}{{ pick('name.syllable', not: a) }}"
    filters: [capitalize]
```

```twig
{{ include('@TilttekGenerator/draw.html.twig', {
    result: tilttek_draw('name.short', 5, true),
    url: path('tilttek_generator_draw', {generator: 'name.short'}),
}) }}
```

```sh
bin/console tilttek:generator:validate
bin/console tilttek:generator:draw name.short --count=5 --unique
```

## Requirements

PHP 8.5 or later, Symfony 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
