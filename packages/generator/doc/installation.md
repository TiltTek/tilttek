# Installation

## Requirements

- PHP 8.5 or later, with the `mbstring` extension, which the grammars use to read accented words.
- `twig/twig` 3.21 or later, whose sandbox compiles and runs the templates.
- `symfony/yaml` 8.2, which reads the YAML documents.
- `tilttek/dice`, which reads and rolls the dice expressions of `roll()`.

## Installing

```sh
composer require tilttek/generator
```

Nothing is registered and nothing is configured: read a catalog and build a `Generator` where you use it. A Symfony
application installs `tilttek/generator-bundle`, which reads the files of its configuration, keeps them in the cache
and builds the generator.

```php
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\FileLoader;

$generator = new Generator(new FileLoader()->catalog(['config/generators']), new Limits(picks: 200), locale: 'fr', cache: 'var/cache/generator');
```

Building the generator compiles and checks every template of the catalog; build it once and keep it for the request or
the process. With a cache directory, Twig writes the compiled templates to PHP files there and loads them, rather than
evaluate them with `eval()`.
