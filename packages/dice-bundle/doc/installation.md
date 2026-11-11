# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2, Doctrine ORM 3.7 with DoctrineBundle 3.
- `tilttek/dice` and `phil/resource` 0.1, installed with the bundle.
- Optional: Symfony Form for the field, Symfony Validator for the constraint, TwigBundle for the templates, Symfony
  Translation for the words of a roll and the labels of the faces.

## Installing

```sh
composer require tilttek/dice-bundle
```

Register the bundle when Flex does not:

```php
// config/bundles.php
return [
    // …
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
];
```

## The journal's table

The bundle maps its entities for Doctrine. Create the table `tilttek_dice_roll` with a migration:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Column | Type | Holds |
|---|---|---|
| `id` | `varchar(32)` | The replay identifier, the primary key |
| `expression` | `text` | The expression in the canonical form |
| `seed` | `varchar(64)` | The seed, in hexadecimal |
| `result` | `json` | The result, as `PoolResult::toArray()` writes it |
| `owner` | `varchar(320)`, null | Who rolled, as a `ResourceReference`; indexed |
| `rolled_at` | `datetime_immutable` | When |
| `rounding` | `varchar(32)`, `NegativeInfinity` by default | How the roll's divisions rounded, by the name of PHP's `RoundingMode` |

## The macros' table

The macros of the sites, the tables and the members are kept in `tilttek_dice_macro`, created by the same migration:

| Column | Type | Holds |
|---|---|---|
| `level` | `varchar(16)` | `site`, `table` or `member`; part of the primary key |
| `owner` | `varchar(320)` | The owner, as a `ResourceReference`; part of the primary key, indexed |
| `name` | `varchar(32)` | The name, in lower case; part of the primary key |
| `expression` | `text` | The expression, in the library's notation |

An application that keeps no roll and no macro sets `store: memory`.
