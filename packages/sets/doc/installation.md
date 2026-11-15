# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource` 0.1, whose `ResourceReference` names a holder.
- `psr/clock` and `psr/event-dispatcher`: the clock of the completions and the dispatcher of the events, both
  optional.

## Installing

```sh
composer require tilttek/sets
```

## Wiring the library

```php
use Tilttek\Sets\Sets;

$sets = new Sets(
    $definitions,   // Contract\DefinitionStore: every version of every set
    $completions,   // Contract\CompletionStore: the completions, one per set, tier and holder
    $clock,         // a PSR-20 clock; UtcClock, the system's in UTC, by default
    $dispatcher,    // a PSR-14 dispatcher, told of each completion; null to dispatch nothing
    $backoff,       // a DeliveryBackoff for deliverPending(); one minute doubled up to a day, by default
);
```

`Sets` keeps nothing between two calls, so one instance serves every request of a worker. `InMemoryDefinitionStore`
suits sets declared in the application's configuration and defined at boot; `InMemoryCompletionStore` keeps
everything in the process, for tests and scripts.

## Resuming deliveries

A completion whose event handler threw stays undelivered until it is told again (see
[Delivery](progress-and-completions.md#delivery)). `Sets::deliverPending()` tells those completions again without
waiting for their holders' holdings to change; a command or a scheduled task of the application calls it at an
interval. The completion store also implements [`Contract\PendingDeliveries`](extension-points.md#implementing-a-store);
`Store\InMemoryCompletionStore` does.

The library depends on no framework. In a Symfony application, `tilttek/sets-bundle` registers `Sets` from its
configuration, with the `tilttek:sets:deliver` command, the `DeliverPendingCompletions` message and its handler, and
the `tilttek_sets` schedule.
