# Installation

## Requirements

- PHP 8.5 or later and Symfony 8.2.
- `tilttek/sets`, whose `Sets` the bundle registers.
- Optional: `symfony/console` for the `tilttek:sets:deliver` command, `symfony/messenger` for the handler of the
  `DeliverPendingCompletions` message, `symfony/scheduler` for the `tilttek_sets` schedule and `symfony/lock` for its
  lock. The bundle registers each only when its package is installed.

## Installing

```sh
composer require tilttek/sets-bundle
composer require symfony/console symfony/messenger symfony/scheduler
```

## Registering the bundle

```php
// config/bundles.php
return [
    // ...
    Tilttek\SetsBundle\TilttekSetsBundle::class => ['all' => true],
];
```

Without configuration, the definitions and the completions are kept in memory, which suits tests. An application
names its stores, as [Configuration](configuration.md) shows: the completion store also implements
`Tilttek\Sets\Contract\PendingDeliveries`, which the command and the schedule need.
