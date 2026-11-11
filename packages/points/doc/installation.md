# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/activity`, the journal of the members' activity that the earning rules read.
- `phil/resource`, the opaque reference of a member (`member:44`).
- `psr/clock`, the interface of the clock that stamps each movement; any PSR-20 clock does, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/points
```

Nothing is registered and nothing is configured: build `Points` and `PointsProjection` over a `Ledger` where you use
them. A Symfony application installs `tilttek/points-bundle`, which keeps the ledger in Doctrine DBAL and builds both
from its configuration.

```php
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Points\PointsProjection;
use Tilttek\Points\Rule\Rules;

$ledger = new InMemoryLedger();
$currencies = new Currencies(new Currency('points'), new Currency('stars', 'P1Y'));
$points = new Points($ledger, $clock, $currencies);
$projection = new PointsProjection($ledger, $currencies, Rules::fromArray([
    'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week']],
], 'America/Toronto'), $clock);
```

`InMemoryLedger` forgets everything with the process: it is for tests, scripts and examples. An application that keeps
its points writes a `Ledger` on its storage ([Writing a ledger](writing-a-ledger.md)) or installs the bundle.
