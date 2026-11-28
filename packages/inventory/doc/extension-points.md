# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\InventoryStore` | Where inventories, items and logs are kept | `Store\InMemoryInventoryStore` in tests |
| `Contract\RuleBook` | The carrying rules of each game system | `Rules\StaticRuleBook`, empty: nothing is weighed |
| `Contract\CarrierCapacity` | The capacity of each carrier | `Rules\DefaultCapacity`: the rules' default |
| `Contract\InventoryObserver` | What to do with a change of an inventory | None |
| `Limits` | Its bounds on inventories per owner, items per inventory, container depth and log entries | 20, 1000, 4 and 1000 |
| `Psr\Clock\ClockInterface` | The moment of each act | — |

## Writing an inventory store

An `InventoryStore` names inventories, owners and carriers by their references written `kind:identifier`, and items by
their keys inside their inventory. It keeps:

- the inventories, by reference — `inventory()`, `saveInventory()`, `removeInventory()`, which removes the inventory's
  items and log with it, and `inventoriesOf($owner)`, the earliest opened first, ties by reference;
- the items, one per inventory and key — `item()`, `saveItem()`, which replaces the item of the same key,
  `removeItem()`, `countItems()`, `items()`, the earliest added first, ties by key, and `carriedBy($carrier)`, the items
  placed directly on a carrier in every inventory, inventory after inventory in the order of their references;
- the logs — `appendLog($entry, $keep)`, which adds the entry then drops the oldest beyond `$keep`, `log($inventory,
  $limit)`, the newest first, the last written first for the same moment, and `eraseFromLog($reference)`, which rewrites
  every entry that names the reference as `LogEntry::without()` gives it.

An item's place is kept as `Place::toString()` writes it and read back with `Place::fromString()`; its burden as three
whole numbers; its container as two nullable whole numbers and a flag. The library checks an inventory's limits,
quantities, containers and loads before it writes: an application that serves concurrent requests runs each act of
`Inventories` in a transaction, or under a lock on the inventory — on both for a transfer —, so that two requests never
take the same arrows.

## Rule books and capacities

A `RuleBook` gives the `CarryRules` of a system key, or null when it declares none; a `CarrierCapacity` gives a
carrier's capacity in an inventory, by the measure of the rules, 0 to 1,000,000,000,000. Both read what they need —
the systems, the variants a table plays with, the character's sheet — and keep no state between two requests beside
what they read. [Carrying rules and loads](carrying.md) shows one of each.

## Observers

An `InventoryObserver` is told an `InventoryChanged` after each act, once the store wrote it, with identifiers only —
the inventory, the item's key, the acting reference — the `Change` and the moment. It keeps no state between two calls.
[The log and erasure](log.md) lists the changes.
