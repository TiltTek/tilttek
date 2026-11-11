# The journal in the database

## The tables

`tilttek_activity_entry`, one row per entry:

| Column | Type | Holds |
|---|---|---|
| `position` | big integer, auto-increment, primary key | The position, never given twice |
| `kind` | string (16) | `recorded` or `cancelled` |
| `idempotency_key` | string (255) | The key; unique with `kind` |
| `member` | string (320) | The member reference, `kind:identifier` |
| `verb` | string (128) | The verb |
| `object` | string (320), nullable | The object reference |
| `quantity` | integer | The quantity |
| `context` | JSON | The context |
| `occurred_at` | date and time | When the gesture happened, in UTC |
| `recorded_at` | date and time | When the entry was written, in UTC |

Indexes: `tilttek_activity_entry_key` (unique: `idempotency_key`, `kind`), `tilttek_activity_entry_member`,
`tilttek_activity_entry_object`, `tilttek_activity_entry_verb` (`verb`, `occurred_at`).

`tilttek_activity_checkpoint`: `projection` (string 64, primary key), `position` (big integer), `updated_at`.

## Writing once

An entry is inserted by a statement that writes nothing when its key and kind are already there, without aborting the
surrounding transaction:

| Platform | Statement |
|---|---|
| PostgreSQL | `INSERT … ON CONFLICT (idempotency_key, kind) DO NOTHING` |
| MySQL, MariaDB | `INSERT IGNORE …` |
| SQLite | `INSERT OR IGNORE …` |
| Others | A plain `INSERT`, whose unique violation counts as "already there" |

The entry is then read back: the caller gets the row the database kept, and whether this call wrote it. On the
"others" platforms, a violation inside a transaction may abort it, as the platform decides.

## Dates and positions

Dates are written in UTC, to the second, and read back in UTC. Positions come from the auto-increment; SQLite's
`AUTOINCREMENT`, PostgreSQL's identity and InnoDB never give a number twice. Two transactions that commit in the other
order than they took their positions can make a catch-up that runs between them skip the earlier position: an
application that writes activity from many concurrent transactions and needs every entry projected runs a rebuild of
the period when it suspects a gap, or catches up from a single writer.

## Reading

Filters are written in SQL: the member, the object and the period compare columns; a verb pattern with a prefix
compares the start of the verb by the platform's `SUBSTRING`, which an underscore in a verb cannot fool as it would a
`LIKE`. A row whose context or columns do not read — written by hand, or by an older version — raises
`UnexpectedValueException` with the column at fault.
