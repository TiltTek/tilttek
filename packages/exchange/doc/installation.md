# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/points`, the ledger the rewards and the transfers move points on, and the currencies of the prices.
- `phil/resource`, the opaque references of members (`member:44`) and items (`card:812`).
- `psr/clock`, the interface of the clock that stamps each exchange; any PSR-20 clock does, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/exchange
```

Nothing is registered and nothing is configured: build `Redemptions`, `Transfers` and `Trades` where you use them,
over the stores and the `Points` of `tilttek/points`. A Symfony application installs `tilttek/exchange-bundle`, which
keeps the stores in Doctrine DBAL and builds the services from its configuration.

```php
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\Transfers;

$redemptions = new Redemptions(InMemoryCatalog::fromArray(['discount-10' => ['price' => 100]]), new InMemoryReservationStore(), $points, $currencies, $clock);
$transfers = new Transfers(new InMemoryTransferLog(), $points, $currencies, $clock, new Guards());
$trades = new Trades(new InMemoryOfferStore(), $inventory, $points, $currencies, $clock, new Guards());
```

The stores in memory forget everything with the process: they are for tests, scripts and examples. An application
that keeps its exchanges writes stores on its storage ([Writing a store](writing-a-store.md)) or installs the bundle.
