# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.
- `tilttek/exchange`, the exchanges themselves, and `tilttek/points`, the ledger they move points on.
- The `Points` and `Currencies` services, which `tilttek/points-bundle` registers; keep its ledger on the same
  connection as the exchanges so that a trade's points move in the trade's transaction.
- `psr/event-dispatcher`; FrameworkBundle provides the dispatcher.
- Optional: `doctrine/orm` for the schema listener, `symfony/console` for the commands, `symfony/form` for the forms,
  `phil/privacy-bundle` for the export and the erasure.

## Enabling the bundle

```sh
composer require tilttek/points-bundle tilttek/exchange-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\PointsBundle\TilttekPointsBundle::class => ['all' => true],
    Tilttek\ExchangeBundle\TilttekExchangeBundle::class => ['all' => true],
];
```

Configure the rewards ([Configuration](configuration.md)), then let Doctrine write the migration of the tables:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Have a scheduler run `bin/console tilttek:exchange:expire` every few minutes, so that unconfirmed reservations give
their points back and unanswered offers free their items.
