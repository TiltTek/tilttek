# The database

## The tables

| Table | One row per | Holds |
|---|---|---|
| `tilttek_collections_copy` | Copy identifier | The item, the owner, the source, the key, when, whether tradable and whether moved |
| `tilttek_collections_completion` | Set and member | When the member completed the set first |
| `tilttek_collections_opening` | Opening key | The member, the pack, the seed, its commitment, the client seed, the items and the copies as JSON lists, when |
| `tilttek_collections_choice` | Member and number of sides | The copy the member rolls |
| `tilttek_collections_daily` | Day (`Y-m-d`) | The item and why: `rule`, `drawn` or `chosen` |
| `tilttek_collections_definition` | Kind and code | The definition as JSON, and its position among those of its kind |

Dates are written in UTC, to the second.

## Concurrency

A copy, a completion and an opening are inserted by a statement that writes nothing when the row is already there
(`ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; a plain
insertion elsewhere, whose unique violation reaches the caller): two processes giving the same key give one copy. A
copy changes hands by an update that names its expected owner, so that two trades of the same copy cannot both pass;
run a trade in a transaction of the connection, as `tilttek/exchange-bundle` does, so that a refused copy rolls the
whole trade back.

## The catalog in the database

With `catalog: doctrine`, `DbalCatalog` serves the catalog from `tilttek_collections_definition` and is the
`WritableCatalog` service: `define(DefinitionKind::Item, 'pirate', [...])` adds or replaces a definition,
`remove(DefinitionKind::Item, 'pirate')` removes one, and the catalog that results is checked whole before anything is
written — an item whose rarity is gone, a pack with nothing to draw, is refused. A definition keeps the position it was
first defined at, so that the rarities keep their order. Define a set before the items that belong to it; a set
without items yet is never complete.
