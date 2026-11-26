# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/dice` 0.3, which gives the seed, its commitment and its randomizer.

## Installing

```sh
composer require tilttek/card-deck
```

## Wiring the library

The library has no service to configure. An application gives `CardTable` its own store — an implementation of
`Contract\GameStore` on its database ([Games and stores](games-and-stores.md#a-store-of-your-own)):

```php
$table = new CardTable(new DoctrineGameStore($connection)); // the application's adapter
```

`CardTable` keeps nothing between two calls: one instance serves every request, in a worker as in a request. Each call
reads the game from the store and replays its journal; a journal of 1 000 gestures replays in a few milliseconds
([Testing](testing.md#benchmarks)).

`Store\InMemoryGameStore` is for tests: it keeps its games in the object, which a worker must never share between
requests.
