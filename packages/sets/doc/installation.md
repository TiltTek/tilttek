# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource` 0.1, whose `ResourceReference` names a holder.
- `psr/clock` and `psr/event-dispatcher`: the clock of the completions and the dispatcher of the events, both
  optional.
- Suggested: `symfony/console`, `symfony/messenger`, `symfony/scheduler` and `symfony/lock`, 8.2 or later, for the
  integration that resumes deliveries; see [Resuming deliveries with Symfony](#resuming-deliveries-with-symfony).

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
);
```

`Sets` keeps nothing between two calls, so one instance serves every request of a worker. `InMemoryDefinitionStore`
suits sets declared in the application's configuration and defined at boot; `InMemoryCompletionStore` keeps
everything in the process, for tests and scripts.

## Resuming deliveries with Symfony

A completion whose event handler threw stays undelivered until it is told again (see
[Delivery](progress-and-completions.md#delivery)). The package ships an optional Symfony integration, under
`Tilttek\Sets\Symfony`, that tells those completions again without waiting for their holders' holdings to change. It
needs no bundle: the application registers the classes it wants as services. Each uses a suggested package:

| Class | What it is | Needs |
|---|---|---|
| `Symfony\DeliverCommand` | The `tilttek:sets:deliver` command | `symfony/console` |
| `Symfony\DeliverPendingCompletions`, `Symfony\DeliverPendingCompletionsHandler` | A message that carries a limit only, and its handler | `symfony/messenger` |
| `Symfony\DeliverySchedule` | The `tilttek_sets` schedule, which sends that message at an interval | `symfony/scheduler`, and `symfony/lock` for its lock |

```sh
composer require symfony/console symfony/messenger symfony/scheduler
```

Every one of them calls `Sets::deliverPending()`, so the completion store also implements
[`Contract\PendingDeliveries`](extension-points.md#implementing-a-store); `Store\InMemoryCompletionStore` does.

### Registering the services

With autoconfiguration, the attributes of the classes do the rest: `#[AsCommand]` registers the command,
`#[AsMessageHandler]` the handler and `#[AsSchedule]` the schedule.

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Tilttek\Sets\Sets:
        arguments:
            $definitions: '@App\Sets\DefinitionStore'   # the application's Contract\DefinitionStore
            $completions: '@App\Sets\CompletionStore'   # its Contract\CompletionStore and Contract\PendingDeliveries
            $clock: '@clock'
            $events: '@event_dispatcher'

    Tilttek\Sets\Symfony\DeliverCommand: ~
    Tilttek\Sets\Symfony\DeliverPendingCompletionsHandler: ~
    Tilttek\Sets\Symfony\DeliverySchedule: ~
```

### The command

```sh
bin/console tilttek:sets:deliver                      # the 100 holders waiting the longest
bin/console tilttek:sets:deliver --limit=1000         # up to 1 000 holders, at most 10 000
bin/console tilttek:sets:deliver --holder=member:1042 # every undelivered completion of one holder
```

It prints how many completions were delivered. When a handler throws, the other holders are told all the same and
the command exits with a failure that says how many completions were delivered and which exception was thrown first,
so that a cron job or a monitor sees it. `--limit` does not apply with `--holder`.

### The schedule

`DeliverySchedule` sends a `DeliverPendingCompletions` message every five minutes, for the 100 holders waiting the
longest. A worker consumes the schedule through its transport, `scheduler_tilttek_sets`:

```sh
bin/console messenger:consume scheduler_tilttek_sets
```

The interval, the limit and a lock are the schedule's constructor arguments:

```yaml
# config/services.yaml
services:
    Tilttek\Sets\Symfony\DeliverySchedule:
        arguments:
            $frequency: '15 minutes'   # a relative format, a number of seconds, or a DateInterval
            $limit: 500                # 1 to 10 000 holders a run
            $lock: '@tilttek_sets.delivery_lock'

    tilttek_sets.delivery_lock:
        class: Symfony\Component\Lock\LockInterface
        factory: ['@lock.factory', 'createLock']
        arguments: ['tilttek_sets_delivery']
```

A run missed while no worker consumed the schedule is run once, not once per interval. With the lock, two workers
that consume the schedule do not both send the message; without it, both may, which the idempotent handlers absorb.
A run whose handler threw ends with `DeliveryFailed`, which the worker logs; the next run tells the rest again.

The message is handled by the worker that consumes the schedule. To handle it elsewhere, route it to another
transport:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Tilttek\Sets\Symfony\DeliverPendingCompletions: async
```

Without the Scheduler, a cron job that runs the command does the same.
