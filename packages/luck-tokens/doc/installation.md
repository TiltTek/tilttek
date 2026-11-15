# Installation

## Requirements

- PHP 8.5 or later, with `mbstring`.
- `phil/resource` 0.1, whose `ResourceReference` names a table, a holder and an actor.
- `psr/clock` and `symfony/clock`, for the time of each entry; `psr/event-dispatcher`, for the events.

## Installing

```sh
composer require tilttek/luck-tokens
```

## Wiring the library

`LuckTokens` takes the rules and the ledger, and optionally an event dispatcher, the access of actors to holders, a
clock and the size of a ledger:

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\SelfAccess;

$tokens = new LuckTokens(
    $rules,               // Contract\TokenRules: the kinds of tokens and the game masters of each table
    $ledger,              // Contract\TokenLedger: the append-only ledger of each table
    $dispatcher,          // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new SelfAccess(),     // Contract\HolderAccess: who spends the tokens of whom; each player their own by default
    new NativeClock(),    // Psr\Clock\ClockInterface: the time of the entries, written in UTC
    100_000,              // the most entries a table's ledger takes
);
```

`LuckTokens` keeps nothing between two calls, so one instance serves every request of a worker. The memory stores,
`InMemoryTokenRules` and `InMemoryTokenLedger`, keep everything in the process, for tests and scripts; an application
keeps the rules and the ledger in its own storage, as [Extension points](extension-points.md) describes.
