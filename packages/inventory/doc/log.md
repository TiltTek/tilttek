# The log and erasure

## The log

Every act on an inventory writes a `LogEntry` in its log, once the store wrote the change:

```php
foreach ($inventories->log($inventory, 20) as $entry) {
    $entry->change;     // Change::Moved
    $entry->item;       // 'arrows.bo'
    $entry->quantity;   // 10
    $entry->from;       // 'carrier:character:12'
    $entry->to;         // 'carrier:character:13'
    $entry->by;         // 'player:1', the acting reference, or null
    $entry->reason;     // 'Ada is down', or null
    $entry->at;
}
```

`log()` reads the newest entries, 1 to 1000, the newest first; entries of the same moment, the last written first. An
inventory keeps its newest `Limits::$logPerInventory` entries, the oldest dropped first, and its log goes when it
closes. A reason is 1 to 200 characters of UTF-8 without control characters nor surrounding spaces.

| `Change` | Item | Quantity | From | To |
|---|---|---|---|---|
| `Opened`, `Updated` | — | — | — | — |
| `Added`, `Increased` | The item | Units added | — | Its place |
| `Decreased`, `Removed` | The item | Units taken away | Its place | — |
| `Moved` | The item that arrived: the new key for part of a stack | Units moved | The old place | The new place |
| `Equipped`, `Unequipped` | The item | — | — | — |
| `TransferredOut` | The item, in the source | Units sent | Its place | The destination inventory's reference |
| `TransferredIn` | The item that arrived, in the destination | Units received | The source inventory's reference | Its place |
| `Forgotten` | The item its erased carrier held | Its quantity | `carrier` | `stash` |

Places are written as `Place::toString()` gives them, and read back by `Place::fromString()`. `Closed` is told to the
observers but logged nowhere: the log goes with the inventory.

## Events

A `Contract\InventoryObserver` is told an `InventoryChanged` after each act, with the inventory's reference, the
`Change`, the item's key, the acting reference and the moment. Names, quantities, places and reasons are never in it: an
observer that relays the event — a notification, a message on a bus — reads the item or the log when it needs them.

## Erasure

`forget()` erases a reference from every inventory:

```php
$inventories->forget($ada);
```

What it carried directly, in any inventory, goes to the stash, unequipped, with what its containers hold, each logged as
`Forgotten`. Every log entry that names it loses it: the acting reference is dropped and a place on it is written
`carrier`. The inventories it owns stay: the application closes them, from `inventoriesOf()`, when they go with it.
