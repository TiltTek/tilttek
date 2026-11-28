# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Inventories` | Opens, runs, reads and erases inventories, their items, loads and logs |
| `Inventory` | An inventory: reference, owner, name, game system, opening moment |
| `Item` | An item under a key: name, quantity, burden, place, container, equipped, adding moment |
| `Burden` | What one unit weighs and the slots a stack takes |
| `Place` | The stash, a carrier or a container, written and read back as text |
| `Container` | What makes an item a container: its bounds and whether its contents weigh |
| `Fill` | What a container holds against its bounds |
| `Measure` | `Weight` or `Slots` |
| `CarryRules` | The carrying rules of a system: measure, default capacity, bands, refusal over capacity |
| `LoadBand` | A band of load, up to a fraction of the capacity |
| `Load` | What a carrier carries, its capacity, band, limit and room |
| `LogEntry` | A line of an inventory's log |
| `Limits` | Inventories per owner, items per inventory, container depth, log entries |
| `InventoryChanged` | Told after each act, identifiers only |
| `Change` | What happened to an inventory |
| `Refusal` | Why an act is refused |
| `Contract\InventoryStore` | Where inventories, items and logs are kept |
| `Contract\RuleBook` | The carrying rules of each system |
| `Contract\CarrierCapacity` | The capacity of each carrier |
| `Contract\InventoryObserver` | What the application does with the events |
| `Store\InMemoryInventoryStore` | The store in memory |
| `Rules\StaticRuleBook` | Carrying rules given by system key |
| `Rules\DefaultCapacity` | The rules' default capacity for every carrier |
| `Exception\InventoryException` | Every exception of the library |
| `Exception\InventoryRefused` | An act refused, with its `Refusal` |
| `Exception\UnknownInventory` | No inventory under a reference, no item under a key |
| `Exception\InvalidInventory` | A value out of bounds |
