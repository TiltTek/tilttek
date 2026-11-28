# tilttek/inventory

**Track what characters and parties carry in play: item quantities, containers, who holds what, weight or slots by the
system's rules, and a change log.**

tilttek/inventory keeps the inventories of a game. The application opens an inventory under a reference it gives,
`inventory:7`, for an owner — a character, `character:12`, or a party, `party:3` —, with the key of its game system,
`srd-5.2`. Each **item** lives under a key, `rope`, `arrows.silver`, with a name, a quantity and a **burden**: what one
unit weighs, in the whole unit the system chooses, and the slots a stack takes, so that one item serves systems that
count weight and systems that count slots.

An item is somewhere: in the **stash** — the camp, the cart, the bank, carried by no one —, on a **carrier**, under
the reference the application gives it, or inside a **container** of the same inventory. A container is an item that
holds others within its bounds by weight and by slots, nests inside other containers to a bounded depth, and may make
what it holds weigh nothing, as a magic bag does. Items move between places, whole or part of a stack under a new key,
a container with what it holds; a carrier equips what it holds directly; an item goes to another inventory by a
transfer — a character's share of the party's loot, the gear left at the inn.

The **load** of a carrier follows the **carrying rules** the application declares for each system: what it counts,
weight or slots, its bands of load as fractions of the carrier's capacity — unencumbered up to a third, encumbered up to
two thirds —, and whether a carrier may go beyond the last band. The capacity of each carrier comes from the
application, read from the character's sheet, or from the rules' default.

Every act writes a line in the inventory's **log**: what changed, the item, how many units, from where to where, who
acted, why, and when, the newest kept within a bound.

It is for a virtual table, a character manager or a game site. It is a plain PHP library whose dependencies are
`phil/resource`, the opaque reference of an inventory, an owner or a carrier, and `psr/clock`. Carriers and owners are
references the application gives: the library never reads a name, an e-mail or an account, and its events carry
identifiers only.

## Status

| Part | State |
|---|---|
| `Inventory`, `Inventories`: open, rename, change system, close, read an owner's inventories, erase a reference | Implemented and tested |
| `Item`, `Burden`, `Place`: items, their quantities, burdens and places; adding, increasing, decreasing, removing | Implemented and tested |
| `Container`, `Fill`: containers, their bounds, nesting and contents that weigh nothing | Implemented and tested |
| Moving whole or part of a stack, equipping, transferring to another inventory | Implemented and tested |
| `CarryRules`, `LoadBand`, `Measure`, `Load`: the load of each carrier by the system's rules | Implemented and tested |
| `LogEntry`: the log of every change, bounded | Implemented and tested |
| `Contract\InventoryStore`, with `Store\InMemoryInventoryStore` | Implemented and tested |
| `Contract\RuleBook`, `Contract\CarrierCapacity`, with `Rules\StaticRuleBook` and `Rules\DefaultCapacity` | Implemented and tested |
| `Contract\InventoryObserver`, `InventoryChanged`: what is told, identifiers only | Implemented and tested |
| `Limits`: inventories per owner, items per inventory, container depth, log entries | Implemented and tested |
| A Symfony bundle: Doctrine store, services, forms and translations | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Items and places](doc/items.md) | Inventories, items, burdens, places, containers, moving, equipping and transferring. |
| [Carrying rules and loads](doc/carrying.md) | What a system counts, load bands, capacities, loads and container fills. |
| [The log and erasure](doc/log.md) | The log of every change, the events, and erasing a reference. |
| [Extension points](doc/extension-points.md) | The store, the rule book, the capacity, observers, limits and the clock. |
| [Exceptions](doc/exceptions.md) | `InventoryException` and its exceptions, with the refusals and their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that uses inventories. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/inventory
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Inventory\Burden;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Container;
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\LoadBand;
use Tilttek\Inventory\Measure;
use Tilttek\Inventory\Place;
use Tilttek\Inventory\Rules\StaticRuleBook;
use Tilttek\Inventory\Store\InMemoryInventoryStore;

$rules = new StaticRuleBook([
    'srd-5.2' => new CarryRules(Measure::Weight, 150, [
        new LoadBand('unencumbered', 1, 3),
        new LoadBand('encumbered', 2, 3),
        new LoadBand('heavily-encumbered', 1),
    ]),
]);
$inventories = new Inventories(new InMemoryInventoryStore(), $clock, $rules);

$inventory = ResourceReference::of('inventory', '7');
$ada = ResourceReference::of('character', '12');
$bo = ResourceReference::of('character', '13');

$inventories->open($inventory, ResourceReference::of('party', '3'), 'The party\'s gear', 'srd-5.2');
$inventories->add($inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($ada), new Container(maxWeight: 30));
$inventories->add($inventory, 'rope', 'Hempen rope', 1, new Burden(10), Place::inside('pack'));
$inventories->add($inventory, 'arrows', 'Arrows', 20, new Burden(1, 1, 20), Place::carriedBy($ada));

$inventories->move($inventory, 'arrows', Place::carriedBy($bo), quantity: 10, as: 'arrows.bo');
$inventories->decrease($inventory, 'arrows', 3, reason: 'shot at the goblins');

$inventories->load($inventory, $ada)?->band?->key;   // 'unencumbered': 5 + 10 + 7
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
