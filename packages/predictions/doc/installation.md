# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/points`, the ledger the points are written in.
- `phil/resource`, whose references name the members.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/predictions
```

## Wiring the library

`Predictions` takes the store, the points and the clock; everything else has a default:

```php
use Tilttek\Predictions\Predictions;

$predictions = new Predictions(
    $store,         // Contract\PredictionStore; Store\InMemoryPredictionStore for tests
    $points,        // Tilttek\Points\Points
    $clock,         // Psr\Clock\ClockInterface
    'stars',        // the currency of tilttek/points; its default when null
    'predictions',  // the issuance account the points come from
);
```

`Predictions` keeps no state between two calls: one instance serves every request, in a worker as in a request. The
store it is given keeps that promise too.

## The store

`Store\InMemoryPredictionStore` keeps nothing past the process: it serves tests. An application keeps its questions
and predictions in its database, through its own `Contract\PredictionStore`, whose contract
[Extension points](extension-points.md) describes.
