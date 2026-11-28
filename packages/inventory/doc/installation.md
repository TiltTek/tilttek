# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, whose references name the inventories, their owners and the carriers.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/inventory
```

## Wiring the library

`Inventories` takes the application's store and clock. Everything else has a default:

```php
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\Limits;
use Tilttek\Inventory\Rules\DefaultCapacity;
use Tilttek\Inventory\Rules\StaticRuleBook;

$limits = new Limits(inventoriesPerOwner: 20, itemsPerInventory: 1000, containerDepth: 4, logPerInventory: 1000);

$inventories = new Inventories(
    $store,                                // Contract\InventoryStore
    $clock,                                // Psr\Clock\ClockInterface
    new StaticRuleBook(['srd-5.2' => $rules]), // Contract\RuleBook: the carrying rules by system
    new DefaultCapacity(),                 // Contract\CarrierCapacity: each carrier's capacity
    $limits,
    [$observer],                           // Contract\InventoryObserver, told of each change
);
```

Without a rule book, no inventory is weighed: items, containers and the log work the same, and `load()` gives null.

`Store\InMemoryInventoryStore` serves tests and applications that rebuild their inventories at each start; an
application that keeps them implements `Contract\InventoryStore` on its storage, as
[extension points](extension-points.md) describe.

`Inventories` keeps no state between two calls: one instance serves every request, in a worker as in a request. The
store, rule book, capacity and observers it is given keep that promise too, beside their storage.
