# Installation

## Requirements

PHP 8.5 or later. The library has no dependency.

## Installing

```sh
composer require tilttek/score-sheets
```

## Wiring the library

The library has no service to configure. An application gives `ScoreKeeper` its own store — an implementation of
`Contract\MatchSheetStore` on its database ([Match sheets](match-sheets.md#a-store-of-your-own)):

```php
$keeper = new ScoreKeeper(new DoctrineMatchSheetStore($connection)); // the application's adapter
```

`ScoreKeeper` and `Scoring\Scorer` keep nothing between two calls: one instance serves every request, in a worker as
in a request. A declaration is read once — from a JSON file the application ships, or from its database — and is a
value it may cache.

`Store\InMemoryMatchSheetStore` is for tests: it keeps its sheets in the object, which a worker must never share
between requests.
