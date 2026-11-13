# Installation

## Requirements

PHP 8.5 or later. The library needs no other package.

## Installing

```sh
composer require tilttek/deck-builder
```

## Wiring the library

```php
use Tilttek\DeckBuilder\{DeckValidator, Decks};

$validator = new DeckValidator(
    $cards,     // Contract\CardCatalog: the application's cards
    $formats,   // Contract\FormatCatalog: the application's formats
);

$decks = new Decks(
    $store,     // Contract\DeckStore: every version of every deck
);
```

`DeckValidator` and `Decks` keep nothing between two calls, so one instance serves every request of a worker. The
in-memory adapters — `Catalog\InMemoryCardCatalog`, `Catalog\InMemoryFormatCatalog` and `Store\InMemoryDeckStore` —
suit cards and formats declared in the application's configuration, tests and scripts; a catalog of thousands of
cards is better read from the application's database, card by card, through its own `Contract\CardCatalog`.

The library reads no clock: the day of a validation and the moment of a version are passed to each call, so that a
deck is checked against the rules of a past or coming event the same way as against today's.
