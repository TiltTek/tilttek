# Installation

## Requirements

- PHP 8.5 or later and Symfony 8.2 or later.
- `tilttek/game-engine` and `doctrine/dbal`, which Composer installs with the bundle.
- For the integrations, each optional: `symfony/cache` for the state cache, `symfony/lock` for the lock of the tables,
  `symfony/mercure-bundle` for the publication of every change, `symfony/framework-bundle` for the endpoints and their
  routes, `symfony/console` for the commands, `doctrine/orm` for the tables in the generated schema,
  `symfony/twig-bundle` and `symfony/ux-twig-component` with the PHIL UX kit for the template of a table,
  `symfony/asset-mapper` and Stimulus for the controller that reads the view again.

## Installing

```sh
composer require tilttek/game-engine-bundle
```

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/tilttek_game_engine.yaml`
with its commented options and imports the routes in `config/routes/tilttek_game_engine.yaml`. It lives in `recipe/`
of the package, ready for the recipes repository. Without it:

```php
// config/bundles.php
return [
    // …
    Tilttek\GameEngineBundle\TilttekGameEngineBundle::class => ['all' => true],
];
```

## The tables of the logs

The logs live in two tables of the configured Doctrine DBAL connection, `tilttek_game_engine_table` and
`tilttek_game_engine_move`. With the ORM, the bundle adds them to the schema it generates, so that
`doctrine:migrations:diff` writes the migration that creates them; without it, `tilttek:game-engine:setup` creates
them, or shows their SQL with `--dump-sql`. See [Storage](storage.md).

## The games

Every service that implements `Tilttek\GameEngine\Contract\Game` is a game, by autoconfiguration; the library's
role-playing scene is one more when `roleplaying_scene` is on. Services are named by the game's own name,
`definition()->game`:

```php
final readonly class Chess implements Game
{
    // …
}
```

## The routes

The endpoints of the views and of the logs are routes the application imports under a prefix:

```yaml
# config/routes/tilttek_game_engine.yaml
tilttek_game_engine:
    resource: '@TilttekGameEngineBundle/config/routes.php'
    prefix: /game-tables
```

They read no table until the application gives its `ViewerResolver`, or opens the audience view to anyone; see
[Live tables](live-tables.md).

## The Stimulus controller

With AssetMapper, the bundle adds its `assets/src` directory under the `@tilttek/game-engine-bundle` namespace; enable
the controller in `assets/controllers.json`:

```json
{
    "controllers": {
        "@tilttek/game-engine-bundle": {
            "game-table": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

## The recipes of the kit

The template of a table is built of the recipes Card and Badge of the PHIL UX kit, installed by the Symfony UX Toolkit:

```sh
php bin/console ux:install card badge --kit phil/ux-kit
```
