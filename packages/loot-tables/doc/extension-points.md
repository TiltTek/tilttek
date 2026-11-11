# Extension points

The library is made of values and of one service; it has no interface for the application to implement.

## The catalogue

Where the tables and boxes come from is the application's: built in code, read by `CatalogReader` from a YAML or JSON
document, or from a database, each time a `LootCatalog`.

## The randomness

`PendingDraw::open()` takes the `Random\Randomizer` the seed is drawn from: a `Random\Engine\Secure` in production, a
seeded engine in tests.

## The roller

`LootDrawer` takes the `FairRoller` of `tilttek/dice`, which takes its `Roller`, its limits and its custom dice: an
application with other limits passes its own.

## Where a draw is kept

The pending draw's seed, its result and its proof are kept by the application, tied to what was sold;
`tilttek/loot-tables-bundle` keeps them in Doctrine.
