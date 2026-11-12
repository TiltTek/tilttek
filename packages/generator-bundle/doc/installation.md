# Installation

## Requirements

- PHP 8.5 or later and Symfony 8.2.
- `tilttek/generator` and `tilttek/dice`, which Composer installs with the bundle.
- For the integrations, each optional: `symfony/framework-bundle` for the draw API and its routes,
  `symfony/rate-limiter` for its rate limits, `symfony/console` for the commands, `symfony/twig-bundle` for the Twig
  function and the template, `symfony/asset-mapper` and Stimulus for the controller that draws again.

## Installing

```sh
composer require tilttek/generator-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\GeneratorBundle\TilttekGeneratorBundle::class => ['all' => true],
];
```

## The files

The bundle reads the YAML (`.yaml`, `.yml`) and JSON (`.json`) files of `config/generators/` of the project when that
directory exists and no path is configured, or the files and directories of `tilttek_generator.paths`. The documents
are those of `tilttek/generator`. A new generator is written there by `tilttek:generator:create`.

## The routes

The draw API is a route the application imports under a prefix:

```yaml
# config/routes/tilttek_generator.yaml
tilttek_generator:
    resource: '@TilttekGeneratorBundle/config/routes.php'
    prefix: /api/generators
```

It answers 404 until `tilttek_generator.api.enabled` is true; see [Draw API](api.md). The Stimulus controller that draws
again in a page calls the same route, for the generators the API opens to anyone.

## The Stimulus controller

With AssetMapper, the bundle adds its `assets/src` directory under the `@tilttek/generator-bundle` namespace; enable the
controller in `assets/controllers.json`:

```json
{
    "controllers": {
        "@tilttek/generator-bundle": {
            "generator": { "enabled": true, "fetch": "eager" }
        }
    }
}
```
