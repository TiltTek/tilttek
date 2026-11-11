<!-- phil-dev source-sha256=54928fe060691f0e1d6fb2cc2dd03fa508b3bd4a8e6af257ad571fce1b1f879a -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- Symfony 8.2 : DependencyInjection et Config.
- Doctrine DBAL 4, par DoctrineBundle, pour les stockages.
- Un répartiteur d'événements PSR-14 — celui de Symfony — pour les événements.
- `tilttek/badges` et `phil/resource`.
- `tilttek/activity-bundle`, pour attribuer des badges à partir de l'activité des membres.

Facultatifs : l'ORM (les tables rejoignent le schéma généré), la Console (les commandes), TwigBundle (les fonctions et
les gabarits), `phil/open-badges-bundle` (les attestations), le composant Translation (les attestations traduites), le
composant Uid (les identifiants des attestations) et `phil/privacy-bundle` (export et effacement).

## Activer le bundle

```sh
composer require tilttek/activity-bundle tilttek/badges-bundle
```

```php
// config/bundles.php
return [
    // ...
    Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
    Tilttek\BadgesBundle\TilttekBadgesBundle::class => ['all' => true],
];
```

Déclarez ensuite les badges ([Configuration](configuration.md)).

## La migration

Avec l'ORM, les quatre tables rejoignent le schéma qu'il génère pour la connexion configurée :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez-les à partir de `BadgesTables::configure()`.
