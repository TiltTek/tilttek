# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.
- `tilttek/luck-tokens`, the tokens themselves, and `phil/resource`, whose references name the tables and the holders.
- `psr/event-dispatcher`; FrameworkBundle provides the dispatcher, and its `clock` service dates the entries.
- Optional: `doctrine/orm` for the schema listener, `symfony/lock` for the locks, `symfony/security-bundle` for the
  access through the security voters, `phil/privacy-bundle` for the erasure of a member.

## Enabling the bundle

```sh
composer require tilttek/luck-tokens-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\LuckTokensBundle\TilttekLuckTokensBundle::class => ['all' => true],
];
```

Configure the tables ([Configuration](configuration.md)), then let Doctrine write the migration of the tables:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create the tables from `LuckTokensTables::tables()` in a migration of your own: it gives them for the
platform of the connection, as [The ledgers in the database](database.md) describes.
