# Items and places

## Inventories

An inventory holds what a character or a party has during play. The application opens it under a reference it gives,
for an owner, with a name of 1 to 100 characters and the key of the game system whose carrying rules weigh it, or none:

```php
$inventories->open($inventory, $party, 'The party\'s gear', 'srd-5.2', $by);
$inventories->rename($inventory, 'The wagon', $by);
$inventories->useSystem($inventory, 'knave', $by);   // or null: never weighed
$inventories->inventoriesOf($party);                // the earliest opened first
$inventories->close($inventory, $by);               // with its items and its log
```

A reference holds one inventory (`Refusal::AlreadyOpen`); an owner holds at most `Limits::$inventoriesPerOwner`
inventories (`Refusal::TooManyInventories`). A character keeps its own inventory and its party another: an item goes
from one to the other by a transfer.

## Items and their burden

An item lives under a key of the application — 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between
them —, with a name of 1 to 100 characters, a quantity of 1 to 1,000,000 and a `Burden`:

```php
use Tilttek\Inventory\Burden;

new Burden(10);          // 10 units of weight each, no slot
new Burden(10, 2);       // and 2 slots for each one: a two-handed sword
new Burden(1, 1, 20);    // 1 slot for each stack of 20: arrows
Burden::none();          // weighs nothing, takes no slot
```

The weight is that of one unit, 0 to 100,000, in the whole unit the system chooses: pounds, tenths of a pound for a
system that weighs coins, tenths of a Bulk when its light items weigh a tenth. The slots, 0 to 1000, are those of a stack
of `perSlot` units, 1 to 10,000; a stack started takes its full slots, so 21 arrows take 2 slots. Both measures are
kept on every item: the carrying rules choose which one counts.

```php
$inventories->add($inventory, 'rope', 'Hempen rope', 1, new Burden(10), Place::carriedBy($ada), by: $gm, reason: 'bought');
$inventories->increase($inventory, 'arrows', 5, $by, 'found');      // the item where it is
$inventories->decrease($inventory, 'arrows', 3, $by, 'shot');       // null once none is left
$inventories->remove($inventory, 'rope', $by, 'burnt');             // the whole entry
```

A key holds one item (`Refusal::ItemExists`); an inventory holds at most `Limits::$itemsPerInventory` items
(`Refusal::TooManyItems`); decreasing by more than the quantity is refused with `Refusal::NotEnough`. A quantity given
is 1 to 1,000,000, and an item never holds more. `items()` reads the items, the earliest added first, ties by key, and
`itemsAt()` those directly at one place.

## Places

`Place` says where an item is:

| Place | Written | What it is |
|---|---|---|
| `Place::stash()` | `stash` | Carried by no one: the camp, the cart, the bank |
| `Place::carriedBy($ada)` | `carrier:character:12` | On a carrier, under the reference the application gives |
| `Place::inside('pack')` | `container:pack` | Inside the container of that key, in the same inventory |

An item added without a place goes to the stash. The library never checks a carrier against a party or a sheet: the
application gives the references that may carry. An item inside a container is carried by whoever carries the
container, however deep.

## Containers

An item is a container when it is added with a `Container`: the most it holds by weight and by slots, each 1 to
1,000,000,000 or none for no bound, and whether what it holds weighs on its carrier.

```php
use Tilttek\Inventory\Container;

$inventories->add($inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($ada), new Container(maxWeight: 30));
$inventories->add($inventory, 'bag', 'Bag of holding', 1, new Burden(15), Place::inside('pack'), new Container(500, null, false));
$inventories->add($inventory, 'anvil', 'Anvil', 1, new Burden(100), Place::inside('bag'));
$inventories->fill($inventory, 'pack');   // weight 15: the bag, not the anvil
```

A container is always a single item (`Refusal::SingleContainer` on `increase()`); it holds what its bounds allow
whatever the system counts (`Refusal::ContainerFull`), what a container inside holds counted against the outer one
unless its contents weigh nothing; it goes inside another container no deeper than `Limits::$containerDepth`
(`Refusal::TooDeep`), never inside itself or what it holds (`Refusal::ContainerCycle`); it goes only once empty
(`Refusal::ContainerNotEmpty`). Putting an item inside an item that is not a container is refused with
`Refusal::NotAContainer`.

## Moving

`move()` puts an item elsewhere in the inventory, a container with everything it holds:

```php
$inventories->move($inventory, 'pack', Place::carriedBy($bo), by: $gm, reason: 'Ada is down');
$inventories->move($inventory, 'arrows', Place::carriedBy($bo), quantity: 10, as: 'arrows.bo');
```

Part of a stack moves under a new key, `$as`, added at the moment of the move; the rest stays under the old key. A
part without a new key, or a new key for the whole stack, is refused with `InvalidInventory`; more than the quantity,
with `Refusal::NotEnough`. The destination is checked as for an item added: the containers it is in, its depth, and
the load of its carrier when the rules refuse going over capacity.

## Equipping

A carrier equips what it holds directly — the sword it wields, the armour it wears, the backpack on its back:

```php
$inventories->equip($inventory, 'sword', $by);
$inventories->unequip($inventory, 'sword', $by);
```

Only an item on a carrier is equipped (`Refusal::NotCarried` for an item in the stash or in a container). An item that
moves, or goes to another inventory, is no longer equipped; equipping an equipped item changes nothing and logs nothing.
The library records the state; what an equipped item does — armour, a bonus, a condition — is the application's.

## Transferring

`transfer()` takes an item, or part of a stack, to another inventory, under its key or `$as`, in the stash when no place
is given:

```php
$inventories->transfer($partyInventory, 'gold-ring', $adaInventory, Place::carriedBy($ada), 1, 'ring', $gm, 'her share');
```

The destination is checked as for an item added; a container goes only once empty (`Refusal::ContainerNotEmpty`); a
transfer to the same inventory is refused with `InvalidInventory`. Both logs record it: `TransferredOut` in the source,
`TransferredIn` in the destination. An application that serves concurrent requests locks both inventories.
