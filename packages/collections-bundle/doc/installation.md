# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5 and its bundle.
- `tilttek/activity-bundle`, to give the item of the day on the members' activity.

## Registering the bundle

```sh
composer require tilttek/collections-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\CollectionsBundle\TilttekCollectionsBundle::class => ['all' => true],
];
```

## The migration

With the ORM installed, the bundle adds its six tables to the schema it generates for the configured connection:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create the tables from `CollectionsTables::configure()`.

## Optional packages

| Package | What it adds |
|---|---|
| `tilttek/activity-bundle` | The projection of the item of the day, kept up to date and rebuilt |
| `phil/almanac` | Items of the day on computed dates |
| `tilttek/exchange-bundle` | Trades of copies between members, through `CollectionsInventory` |
| `tilttek/dice-bundle` | The dice a member chose, through `CollectionDiceAppearance` |
| `symfony/console` | The commands |
| `symfony/twig-bundle`, `symfony/translation` | The Twig functions, the templates and their texts |
| `phil/privacy-bundle` | The export and erasure of a member |
