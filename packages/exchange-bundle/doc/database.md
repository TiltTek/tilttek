# The exchanges in the database

## The tables

| Table | Holds |
|---|---|
| `tilttek_exchange_reward` | The catalogue, with `catalog: doctrine`: code, price, currency, stock, period, limit, conditions as a JSON list |
| `tilttek_exchange_reservation` | One row per reservation, its key the primary key |
| `tilttek_exchange_stock_lock` | One row per reward with a stock or a limit, updated by each reservation to hold its lock |
| `tilttek_exchange_transfer` | The journal of transfers, its position an auto-increment, its key unique |
| `tilttek_exchange_offer` | One row per offer, its key the primary key |
| `tilttek_exchange_offer_item` | The items of each offer, in order on each side |
| `tilttek_exchange_hold` | One row per item an open offer holds, the item the primary key |

`ExchangeSchemaListener` adds them to the schema the ORM generates for the exchanges' connection, the catalogue's table
only with `catalog: doctrine`, so that `doctrine:migrations:diff` writes them and `doctrine:schema:validate` does not
ask to drop them.

## Written once, under a lock

- A reservation of a reward with a stock or a limit runs in a transaction that first updates the reward's lock row,
  which holds the row's write lock — or SQLite's lock of the whole database — until the transaction ends: two
  reservations of the same reward count the units held one after the other.
- Rows that must be written once are inserted by a statement that writes nothing when the key is there:
  `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; a plain
  insertion elsewhere, whose unique violation reaches the caller.
- An offer holds each offered item by inserting it into `tilttek_exchange_hold`: an item another open offer holds is
  refused and the offer's transaction rolled back.
- A reservation leaves `paying` and then `reserved`, and an offer leaves `open`, by an update that names the status it
  leaves: only one process moves it. A paying reservation is written before its points are spent and becomes
  reserved once they are.

Moments are columns of the type `datetime_utc_immutable`, and their parameters are bound with it: a moment of any
zone is stored in UTC, to the second, compared in UTC, and read back in UTC.

## One transaction

`DbalTransaction` runs the steps of an acceptance and a counter-offer in one transaction of the connection. The ledger
of `tilttek/points-bundle` takes part when it is on the same connection, and so does an inventory kept there.
