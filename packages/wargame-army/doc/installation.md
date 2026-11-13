# Installation

## Requirements

PHP 8.5 or later. The library needs no other package.

## Installing

```sh
composer require tilttek/wargame-army
```

## Wiring the library

```php
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Export\{HtmlExport, TextExport};

$validator = new ArmyValidator(
    $catalogues,       // Contract\CatalogueProvider: the application's catalogues, by code
    ...$constraints,   // Contract\Constraint: the application's own rules, checked on every list
);

$text = new TextExport();
$html = new HtmlExport();
```

`ArmyValidator` and the exports keep nothing between two calls, so one instance serves every request of a worker.
Reading a catalogue of 2 000 units with ten options each takes a noticeable fraction of a second: an application reads
its catalogues once — at boot, or from its cache — and gives them to `Catalog\InMemoryCatalogueProvider` or to a
provider of its own.
