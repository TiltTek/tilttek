# Installation

## Requirements

- PHP 8.5 or later, with `ext-sodium`.
- `phil/url` 0.1, which checks the addresses of the relays.
- `psr/clock` and `psr/event-dispatcher`: the clock and the dispatcher of `RaffleDrawn`, both optional.
- `symfony/http-client-contracts`, and an HTTP client such as `symfony/http-client` for the drand adapter.

## Installing

```sh
composer require tilttek/raffle symfony/http-client
```

## Wiring the library

```php
use Tilttek\Raffle\Drand\{DrandBeacon, DrandChains};
use Tilttek\Raffle\Raffles;

$raffles = new Raffles(
    $store,              // Contract\RaffleStore: the raffles and their entrants
    $beacon,             // Contract\RandomnessBeacon: needed for a raffle with a prize; null without one
    $dispatcher,         // a PSR-14 dispatcher, told of each draw; null to dispatch nothing
    $clock,              // a PSR-20 clock; the system's by default
    $randomizer,         // the source of the organisers' seeds; the system's secure source by default
    margin: 30,          // the seconds between the closing of the entries and the beacon round
);

$beacon = new DrandBeacon($httpClient, DrandChains::quicknet(), DrandChains::RELAYS);
```

Without a beacon, `Raffles` runs raffles without a stake only. `Raffles`, `RaffleVerifier` and `DrandBeacon` keep
nothing between two calls, so one instance serves every request of a worker; `InMemoryRaffleStore` keeps everything in
the process, for tests and scripts.
