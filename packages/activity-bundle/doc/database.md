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
`AUTOINCREMENT`, PostgreSQL's identity and InnoDB never give a number twice. A position is taken when the row is
inserted, before its transaction commits: two transactions that commit in the other order than they took their
positions leave, for a while, a missing position below a visible entry.

The reading waits for such a gap. `read()` hands no entry past it and `lastPosition()` is the position below it, until
the entry above the gap was written `gap_wait` ago — ten seconds by default —: by then the transaction that took the
missing position has committed, or it never will — rolled back, a duplicate key inserted by two processes at the same
moment, a member erased. So a catch-up never moves a checkpoint past an entry not yet committed. The entries are read
from the end of the journal while they are younger than `gap_wait`; an older one ends the search.

- An entry behind a gap waits for the next catch-up after the wait: with `projections: live`, the next entry, the same
  request repeated, or `tilttek:activity:catch-up` on a schedule.
- A transaction that holds its position longer than `gap_wait` is still passed: an application with such transactions
  raises `gap_wait`, or runs a rebuild of the period.
- The age is measured on the clock of the reader against `recorded_at`, written by the clock of the writer, to the
  second: servers whose clocks drift apart by more than a second or two raise `gap_wait` as much.
- A gesture recorded again is not inserted again: the entry already there is read first, so a duplicate takes no
  position and leaves no gap.

## Reading

Filters are written in SQL: the member, the object and the period compare columns; a verb pattern with a prefix
compares the start of the verb by the platform's `SUBSTRING`, which an underscore in a verb cannot fool as it would a
`LIKE`. A row whose context or columns do not read — written by hand, or by an older version — raises
`UnexpectedValueException` with the column at fault.
