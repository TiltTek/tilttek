# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, whose references name the holders.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/crafting
```

## Wiring the library

`Crafter` takes the recipe book, the application's inventory and its clock:

```php
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\Policy\ProtectedItems;
use Tilttek\Crafting\RecipeBook;

$book = RecipeBook::fromArray($definition, new ProtectedItems(['gold-die']));

$crafter = new Crafter(
    $book,
    $inventory,                            // Contract\Inventory
    $clock,                                // Psr\Clock\ClockInterface
    [$observer],                           // Contract\CraftObserver, told of each craft
);
```

The book comes from the site's configuration or its database, as [recipes](recipes.md) describe.
`Store\InMemoryInventory` serves tests and applications that rebuild their inventories at each start; an application
implements `Contract\Inventory` on the storage that already holds its items, as [extension points](extension-points.md)
describe.

`Crafter` and `RecipeBook` keep no state between two calls: one instance serves every request, in a worker as in a
request. The inventory and observers they are given keep that promise too, beside their storage.
