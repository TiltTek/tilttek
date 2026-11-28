# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\Inventory` | The holders' items and the crafts applied to them | `Store\InMemoryInventory` in tests |
| `Contract\ConsumptionPolicy` | The items no craft consumes | Every item may be consumed |
| `Contract\CraftObserver` | What to do once a craft was applied | None |
| `Psr\Clock\ClockInterface` | The moment of each craft | — |

## Writing an inventory

The items stay where the application keeps them — the copies of `tilttek/collections`, a character's inventory, the
stock of a larp's economy —, and its `Contract\Inventory` reads and changes them:

- `holdings($holder)` gives what the holder has, one `Holding` per item, with the tags of the item — from the catalog
  that defines the items, most often;
- `find($key)` returns the craft recorded under a key, or none;
- `apply($craft)` checks that the holder still has the items the craft consumes and uses, then removes those it
  consumes, adds those it gives back and produces, and records the craft under its key, in one transaction; it returns
  false, changing nothing, when a craft is already recorded under the key, and throws `Exception\CraftRefused`,
  changing nothing, when an item is missing.

The crafter plans from what `holdings()` read, then `apply()` checks again in its transaction: two crafts of one holder
at once never spend an item twice. A relational inventory keeps a unique index on the key of the crafts. An inventory
of objects that have identifiers keeps the object named by `Craft::$evolved` under its produced item, rather than
removing it and adding another, when it wants an evolved item to stay the same object.

## Consumption policies

A `ConsumptionPolicy` answers `mayConsume($item)`. `Policy\ProtectedItems` takes a fixed list; a site whose shop sells
items answers from its catalog. The book asks it for every consumed ingredient named by its code, and the crafter for
every item a consumed tag might take.

## Observers

A `CraftObserver` is told a `Crafted` once a craft was applied: the holder, the recipe, the key and the moment. An
observer relays it — an achievement, a line of the activity, a message on a bus — and reads the craft back from the
inventory by its key when it needs the items. It keeps no state between two calls.
