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
);
```

`Sets` keeps nothing between two calls, so one instance serves every request of a worker. `InMemoryDefinitionStore`
suits sets declared in the application's configuration and defined at boot; `InMemoryCompletionStore` keeps
everything in the process, for tests and scripts.
