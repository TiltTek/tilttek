# The ledger in the database

## The tables

| Table | Columns | Indexes |
|---|---|---|
| `tilttek_points_movement` | `position` (auto-increment), `movement_key` (400), `kind` (16), `currency` (32), `debit` and `credit` (340), `amount` (bigint), `occurred_at`, `recorded_at`, `cause` (255, null), `reverses` (400, null) | unique `movement_key`; `debit, currency`; `credit, currency`; `cause`; `kind, occurred_at` |
| `tilttek_points_lock` | `account` (340), `currency` (32), `payments` (bigint) | primary key on `account, currency` |

The ledger has no balance column: a balance is the sum of an account's movements, computed in SQL. A lock row holds
no amount either: it counts the payments made from the account, and exists so that each payment can lock it.

## Written once

`DbalLedger` looks the key up first; when it is not there, it inserts the movement by a statement that writes nothing
if another process wrote the same key in between, without aborting the transaction around it:

| Platform | Insertion |
|---|---|
| PostgreSQL | `INSERT … ON CONFLICT (movement_key) DO NOTHING` |
| MySQL, MariaDB | `INSERT IGNORE …` |
| SQLite | `INSERT OR IGNORE …` |
| Others | `INSERT …`, whose unique violation in such a race reaches the caller |

The movement is then read back: the caller gets the row the database kept, and `MovementConflict` when that row is
another movement.

## Payments

A spending or a transfer runs in a transaction that inserts the payer's lock row if it is missing, then updates it —
the update holds the row's write lock on PostgreSQL, MySQL, MariaDB and SQL Server, and SQLite's lock of the whole
database, until the transaction ends —, and only then looks for the movement's key, computes the balance, and writes
the movement only if the balance covers it. Two payments from the same account at the same moment are checked one after
the other, and the second one reads the movements the first one committed; a lock row that cannot be updated stops the
payment with a `LogicException`.

On MySQL and MariaDB, whose default isolation, REPEATABLE READ, holds a transaction's reads to the snapshot of its first
read, the payment's transaction runs at READ COMMITTED. A payment made inside a transaction the application already
opened cannot change its isolation: it reads the movement and the balance with `LOCK IN SHARE MODE`, which reads the
latest committed rows whatever the transaction read before.

## Dates

Moments are written in UTC, to the second, and read back in UTC. The bounds of a cap's period are compared in UTC too,
whatever the timezone they were computed in.

## Rows that do not read

A row whose amount or position is not an integer, whose text column is not text, or whose values the library refuses
— an unknown kind, a negative amount — raises an exception rather than being read as something else.
