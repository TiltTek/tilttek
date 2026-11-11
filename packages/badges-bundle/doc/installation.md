# Installation

## Requirements

- PHP 8.5 or later.
- Symfony 8.2: DependencyInjection and Config.
- Doctrine DBAL 4, through DoctrineBundle, for the stores.
- A PSR-14 event dispatcher — Symfony's — for the events.
- `tilttek/badges` and `phil/resource`.
- `tilttek/activity-bundle`, to award badges from the members' activity.

Optional: the ORM (the tables join the generated schema), the Console (the commands), TwigBundle (the functions and
templates), `phil/open-badges-bundle` (the credentials), the Translation component (translated credentials), the Uid
component (the credentials' identifiers) and `phil/privacy-bundle` (export and erasure).

## Enabling the bundle

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

Then declare the badges ([Configuration](configuration.md)).

## The migration

With the ORM, the four tables join the schema it generates for the configured connection:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Without the ORM, create them from `BadgesTables::configure()`.
