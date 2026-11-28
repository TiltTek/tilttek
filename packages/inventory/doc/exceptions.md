# Exceptions

Every exception of the library implements `Exception\InventoryException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `InventoryRefused` | The inventory's rules refuse the act; `refusal` says why | The refusal's message, below |
| `UnknownInventory` | No inventory holds the reference, or no item the key | `No inventory is known as "…".`, `The inventory "…" holds no item under the key "…".` |
| `InvalidInventory` | A name, a key, a reason, a quantity, a burden, a container, a place, carrying rules, a capacity or a limit out of bounds; part of a stack moved without a new key; a transfer to the same inventory | What is out of bounds, and the bound |

`InventoryRefused` extends `DomainException`, `UnknownInventory` `OutOfBoundsException`, `InvalidInventory`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `AlreadyOpen` | An inventory already holds this reference. |
| `TooManyInventories` | This owner holds as many inventories as allowed. |
| `ItemExists` | The inventory already holds an item under this key. |
| `TooManyItems` | The inventory holds as many items as allowed. |
| `NotEnough` | The inventory holds fewer of this item than asked. |
| `NotAContainer` | This item is not a container. |
| `ContainerCycle` | A container cannot go inside itself or what it holds. |
| `TooDeep` | Containers would nest deeper than allowed. |
| `ContainerFull` | The container cannot hold this much. |
| `ContainerNotEmpty` | The container still holds items. |
| `SingleContainer` | A container is always a single item. |
| `NotCarried` | Only an item a carrier holds in hand or on them can be equipped. |
| `OverCapacity` | The carrier cannot carry this much. |

A new value is checked before the limits and the keys already taken: opening an inventory or adding an item with an
invalid name or key throws `InvalidInventory` even when the reference or the key is already taken. A container the
destination names but the inventory does not hold throws `UnknownInventory`.
