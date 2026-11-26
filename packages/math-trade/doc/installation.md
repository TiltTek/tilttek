# Installation

## Requirements

PHP 8.5 or later. The library has no other dependency: the seed is drawn with the `Random` extension of PHP, and the
lists are read with its JSON functions.

## Installing

```sh
composer require tilttek/math-trade
```

## Using the library

Nothing needs wiring. `TradeSolver` and `TradeListJson` take no argument and keep no state between two calls: one
instance serves every request, in a worker as in a request.

```php
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\TradeSolver;

$list = new TradeListJson()->decode($json);
$result = new TradeSolver()->solve($list, $seed);
```

The solver's time grows with the size of the list — see [Testing](testing.md#complexity) —: an application that lets
members upload lists solves them outside the request, in a Messenger handler for instance, with a message that
carries the identifier of the trade and nothing else.
