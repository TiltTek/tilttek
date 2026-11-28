# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory store and the
`MockClock` of `symfony/clock`: opening, renaming, changing system and closing, at the edge of the inventories an owner
holds; items added, increased, decreased and removed, at the edges of their quantities and of the items an inventory
holds; places, containers and their bounds by weight and by slots, nested containers, contents that weigh nothing, the
depth limit and cycles; moving whole and part of a stack, equipping, transferring; burdens by weight and by stacks of
slots; carrying rules, their bands and fractions, capacities from the carrier, loads and over-capacity refusals; the
log, its order, its bound and its reasons; erasing a reference; the order items, inventories and log entries are read
in; and the events told. In the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that uses inventories

Build the service on the in-memory store and a clock the test moves:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\Rules\StaticRuleBook;
use Tilttek\Inventory\Store\InMemoryInventoryStore;

$clock = new MockClock('2026-12-01 19:00:00', 'UTC');
$inventories = new Inventories(new InMemoryInventoryStore(), $clock, new StaticRuleBook(['srd-5.2' => $rules]));

$inventories->open($inventory, $character, 'Gear', 'srd-5.2');
$clock->modify('+1 hour');
$inventories->add($inventory, 'rope', 'Rope');
```

A `Contract\InventoryObserver` that keeps the events it is told lets a test read what was told; a
`Contract\CarrierCapacity` that reads a table gives each carrier the capacity a test needs. A store of the application
passes the same tests when the in-memory store is replaced by it in a copy of the suite.
