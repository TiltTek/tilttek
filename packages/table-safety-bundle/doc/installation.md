# Installation

```sh
composer require tilttek/table-safety-bundle
```

```php
// config/bundles.php
Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
Tilttek\TableSafetyBundle\TilttekTableSafetyBundle::class => ['all' => true],
```

The recipe in `recipe/0.1` registers the bundle and copies its configuration and routes; until it is published on a
recipes repository, write them by hand:

```yaml
# config/routes/tilttek_table_safety.yaml
tilttek_table_safety:
    resource: '@TilttekTableSafetyBundle/config/routes.php'
```

## Who takes part in a table

The bundle does not know the application's members nor its tables. Implement `TableAccess` and name the service in the
configuration; without it, every screen is refused.

```php
use Phil\Resource\ResourceReference;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;

final readonly class Tables implements TableAccess
{
    public function participant(ResourceReference $table): ?ResourceReference
    {
        // The current member's reference when they take part in the table, else null.
    }

    public function isGameMaster(ResourceReference $table): bool
    {
        // Whether the current member runs the table.
    }
}
```

```yaml
tilttek_table_safety:
    table_access: App\TableSafety\Tables
```

## The tables

With the ORM, `doctrine:migrations:diff` writes the migration of the four tables of [Database](database.md); without
it, create them from `TableSafetyTables::configure()`.

## The screens

The screens extend the application's layout — `base.html.twig` unless the configuration names another — and fill its
block `body`. They are built of the recipes `Card`, `Badge`, `Button` and `Alert` of a UX Toolkit kit, such as the PHIL
UX kit:

```sh
php bin/console ux:install card badge button alert --kit=phil
```

The Stimulus controller ships in `assets/`; with Symfony UX's StimulusBundle and the AssetMapper, it is enabled as
`tilttek--table-safety--signals`.

## The printable cards as a PDF

The printable cards are a page to print; with `phil/paged-media`, the bundle also writes them as a PDF:

```sh
composer require phil/paged-media
```

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31, with FrameworkBundle's sessions, forms and
CSRF protection. Mercure, the console, the ORM, `phil/paged-media` and `phil/privacy-bundle` are optional.
