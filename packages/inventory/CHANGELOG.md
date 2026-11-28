# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/inventory` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Inventory`: an inventory under the application's reference, for an owner — a character or a party —, with a name of
  1 to 100 characters and the key of its game system or none.
- `Inventories`: opening, renaming, changing the system and closing an inventory; reading an owner's inventories, the
  items and the items at a place; erasing a reference from every inventory and log. The application decides who may act
  and passes the acting reference, which the log and the events carry.
- `Item` and `Burden`: items under keys, a quantity of 1 to 1,000,000, the weight of one unit in the system's whole unit
  and the slots of a stack, so that one item serves systems that count weight and systems that count slots; adding,
  increasing, decreasing and removing.
- `Place`: the stash, a carrier under the application's reference, or a container of the same inventory, written and
  read back as text.
- `Container` and `Fill`: containers with bounds by weight and by slots, nested to a bounded depth, never inside
  themselves, whose contents may weigh nothing; what a container holds against its bounds.
- Moving an item whole or part of a stack under a new key, a container with what it holds; equipping what a carrier
  holds directly; transferring an item or part of a stack to another inventory.
- `CarryRules`, `LoadBand`, `Measure` and `Load`: the carrying rules of a system — weight or slots, 1 to 10 load bands
  as exact fractions of the carrier's capacity, refusal over capacity or not — and the load of each carrier, in whole
  numbers.
- `LogEntry`: the log of every change — item, units, from where to where, who, why, when —, the newest kept within a
  bound and read the newest first.
- `Limits`: inventories per owner, items per inventory, container depth and log entries per inventory, by default 20,
  1000, 4 and 1000.
- `Contract\InventoryStore`, with `Store\InMemoryInventoryStore`.
- `Contract\RuleBook` and `Contract\CarrierCapacity`, with `Rules\StaticRuleBook` and `Rules\DefaultCapacity`.
- `Contract\InventoryObserver`, told `InventoryChanged` with its `Change`, identifiers only.
- Exceptions under one `InventoryException` interface: `InventoryRefused` with its `Refusal`, `UnknownInventory` and
  `InvalidInventory`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
