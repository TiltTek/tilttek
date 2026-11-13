<!-- phil-dev source-sha256=715ca416e0f2a04f1f249a2357920111ebb54e3b648d806602f288ce5ffdb645 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.
- `tilttek/game-engine` et `doctrine/dbal`, que Composer installe avec le bundle.
- Pour les intégrations, chacune facultative : `symfony/cache` pour le cache d'états, `symfony/lock` pour le verrou des
  tables, `symfony/mercure-bundle` pour la publication de chaque changement, `symfony/framework-bundle` pour les points
  de terminaison et leurs routes, `symfony/console` pour les commandes, `doctrine/orm` pour les tables dans le schéma
  généré, `symfony/twig-bundle` et `symfony/ux-twig-component` avec le kit PHIL UX pour le gabarit d'une table,
  `symfony/asset-mapper` et Stimulus pour le contrôleur qui relit la vue.

## Installer

```sh
composer require tilttek/game-engine-bundle
```

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/tilttek_game_engine.yaml` avec
ses options commentées et importe les routes dans `config/routes/tilttek_game_engine.yaml`. Elle se trouve dans
`recipe/` du paquet, prête pour le dépôt des recettes. Sans elle :

```php
// config/bundles.php
return [
    // …
    Tilttek\GameEngineBundle\TilttekGameEngineBundle::class => ['all' => true],
];
```

## Les tables des journaux

Les journaux vivent dans deux tables de la connexion Doctrine DBAL configurée, `tilttek_game_engine_table` et
`tilttek_game_engine_move`. Avec l'ORM, le bundle les ajoute au schéma qu'il génère, de sorte que
`doctrine:migrations:diff` écrit la migration qui les crée ; sans l'ORM, `tilttek:game-engine:setup` les crée, ou en
montre le SQL avec `--dump-sql`. Voir [Stockage](storage.md).

## Les jeux

Chaque service qui implémente `Tilttek\GameEngine\Contract\Game` est un jeu, par autoconfiguration ; la scène de jeu de
rôle de la bibliothèque en est un de plus quand `roleplaying_scene` est activée. Les services sont nommés par le nom du
jeu lui-même, `definition()->game` :

```php
final readonly class Chess implements Game
{
    // …
}
```

## Les routes

Les points de terminaison des vues et des journaux sont des routes que l'application importe sous un préfixe :

```yaml
# config/routes/tilttek_game_engine.yaml
tilttek_game_engine:
    resource: '@TilttekGameEngineBundle/config/routes.php'
    prefix: /game-tables
```

Elles ne lisent aucune table tant que l'application ne donne pas son `ViewerResolver`, ou n'ouvre pas la vue du public
à tous ; voir [Tables en direct](live-tables.md).

## Le contrôleur Stimulus

Avec AssetMapper, le bundle ajoute son répertoire `assets/src` sous l'espace de noms `@tilttek/game-engine-bundle` ;
activez le contrôleur dans `assets/controllers.json` :

```json
{
    "controllers": {
        "@tilttek/game-engine-bundle": {
            "game-table": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

## Les recettes du kit

Le gabarit d'une table est construit avec les recettes Card et Badge du kit PHIL UX, installées par le Toolkit de
Symfony UX :

```sh
php bin/console ux:install card badge --kit phil/ux-kit
```
