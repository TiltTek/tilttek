# Installation

## Requirements

PHP 8.5 or later with the sodium extension, Symfony 8.2 with its Workflow component, Doctrine ORM 3.8 and
DoctrineBundle, `phil/commerce-bundle`, whose order workflow and option drawers the bundle plugs into, and
`tilttek/loot-tables`. The screens and the production sheet are optional: the bundle registers
each when its packages are installed.

| Package | For |
|---|---|
| `symfony/form`, `symfony/options-resolver` | `PhysicalDiceType`, the form of the physical dice |
| `symfony/ux-twig-component`, `symfony/twig-bundle` | `TilttekLootTables:Commitments`, the buyer's draws, and `TilttekLootTables:PhysicalDice`, the form on the register's page |
| `phil/ux-kit` | The recipes `Card` and `Badge` the buyer's draws are drawn with, through the Symfony UX Toolkit |
| `symfony/console` | `tilttek:loot-tables:replay`, the command that rolls a draw again |
| `symfony/translation` | The words of the form and the components, in English and French |
| `phil/paged-media` | `LootSheetPdf`, the production sheet |

## Installing

```sh
composer require tilttek/loot-tables-bundle
```

The recipe in `recipe/0.1` registers the bundle and copies its configuration; until it is published on a recipes
repository, enable the bundle after the commerce core by hand:

```php
// config/bundles.php
return [
    // …
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Tilttek\LootTablesBundle\TilttekLootTablesBundle::class => ['all' => true],
];
```

and write its configuration, `config/packages/tilttek_loot_tables.yaml`, as [the configuration](configuration.md)
explains.

For the components, register `TwigComponentBundle`, and install the recipes of the UX kit the buyer's draws are drawn
with:

```sh
php bin/console ux:install card badge
```

## The table of the draws

The bundle maps its entity for Doctrine when DoctrineBundle is installed. Create the table `tilttek_loot_draw` with a
migration:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

| Column | Holds |
|---|---|
| `id` | 32 hexadecimal digits derived from the order, the line, the option and the unit |
| `order_number`, `line`, `option_code`, `unit` | which unit of which option the draw is for; `order_number` is indexed |
| `drawer` | the drawer the option names |
| `seed`, `commitment` | the secret seed, published in the proof once revealed, and its SHA-256, shown before the payment; `null` for physical dice |
| `texts` | the entries a text is drawn from, as they were when committed |
| `result` | the source and the faces, once revealed or entered |
| `proof` | the published proof of a verifiable draw |
| `drawn` | what the order line keeps |
| `created_at`, `revealed_at` | when it was committed or entered, and revealed |

The seed of a draw not yet revealed is a secret: whoever reads it knows the draw before the payment. Keep the table
out of reach of what the buyers see, as any table of the store.
