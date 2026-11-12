<!-- phil-dev source-sha256=b3ce5235f191d035f1c8b227b0f0a070677a7b04e780be87891d49a3dec4e389 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent et Symfony 8.2.
- `tilttek/generator` et `tilttek/dice`, que Composer installe avec le bundle.
- Pour les intégrations, chacune facultative : `symfony/framework-bundle` pour l'API de tirage et ses routes,
  `symfony/rate-limiter` pour ses limites de débit, `symfony/console` pour les commandes, `symfony/twig-bundle` pour la
  fonction Twig et le gabarit, `symfony/asset-mapper` et Stimulus pour le contrôleur qui refait le tirage.

## Installer

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

## Les fichiers

Le bundle lit les fichiers YAML (`.yaml`, `.yml`) et JSON (`.json`) de `config/generators/` du projet quand ce
répertoire existe et qu'aucun chemin n'est configuré, ou sinon les fichiers et les répertoires de
`tilttek_generator.paths`. Les documents sont ceux de `tilttek/generator`. `tilttek:generator:create` y écrit un
nouveau générateur.

## Les routes

L'API de tirage est une route que l'application importe sous un préfixe :

```yaml
# config/routes/tilttek_generator.yaml
tilttek_generator:
    resource: '@TilttekGeneratorBundle/config/routes.php'
    prefix: /api/generators
```

Elle répond 404 tant que `tilttek_generator.api.enabled` n'est pas vrai ; voir [API de tirage](api.md). Le contrôleur
Stimulus qui refait le tirage dans une page appelle la même route, pour les générateurs que l'API ouvre à tout le monde.

## Le contrôleur Stimulus

Avec AssetMapper, le bundle ajoute son répertoire `assets/src` sous l'espace de noms `@tilttek/generator-bundle` ;
activez le contrôleur dans `assets/controllers.json` :

```json
{
    "controllers": {
        "@tilttek/generator-bundle": {
            "generator": { "enabled": true, "fetch": "eager" }
        }
    }
}
```
