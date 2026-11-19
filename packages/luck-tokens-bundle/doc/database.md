# The ledgers in the database

## The tables

| Table | Holds |
|---|---|
| `tilttek_luck_tokens_entry` | One row per entry of every ledger: the table and the position — the primary key —, the operation, the type, the kind, the holder, the change (`delta`), the balance, the actor, the time, the reason and the session |
| `tilttek_luck_tokens_rules` | The rules of each table as JSON, with `rules: doctrine` |

`LuckTokensSchemaListener` adds them to the schema the ORM generates for the tokens' connection, the rules' table only
with `rules: doctrine`, so that `doctrine:migrations:diff` writes them and `doctrine:schema:validate` does not ask to
drop them. Without the ORM, `LuckTokensTables::tables($platform)` gives them for a migration of your own.

| Index | Columns | Answers |
|---|---|---|
| Primary key | `table_ref`, `position` | The last position, the ledger in order, two writers at the same position |
| `…_balance` | `table_ref`, `token_kind`, `holder`, `position` | A balance: the holder's last entry of the kind |
| `…_operation` | `table_ref`, `operation_id`, `position` | The entries of an operation sent again, in order |
| `…_session` | `table_ref`, `session_key` | Whether a session was started |
| `…_holder`, `…_actor` | `holder`; `actor` | The erasure of a member, in every table |

Each entry carries the balance it leaves: a balance is one row read by its index, whatever the length of the ledger.
References are 320 characters, identifiers 64; times are the library's text in UTC to the microsecond,
`2026-11-13T20:15:03.250000Z`, which sorts as it reads. On MySQL and MariaDB, the text columns take the `utf8mb4_bin`
collation, so that `member:Ana` and `member:ana` are two holders, as they are in PHP; SQLite and PostgreSQL compare byte
by byte already.

## Writers at once

`LuckTokens` reads the last position, checks the operation against the balances, and asks the store to append its
entries right after that position. `DbalTokenLedger::append()` opens a transaction, reads the last position again, and
inserts the entries:

- another position read there is a `LedgerConflict`, and nothing is written;
- an insertion that meets an entry of the same table and position — a writer that committed in between — fails on the
  primary key; a deadlock or a lock wait that times out fails as well; each rolls the transaction back and becomes a
  `LedgerConflict`.

The library then reads the ledger again — the same operation done meanwhile by another request is a replay — and
checks again, three times in all. The tests run four processes granting to the same holder at once, on SQLite, MySQL
and PostgreSQL: every grant is written once, at positions that follow one another. A table where many players spend at
the same moment can also take a lock, see [Locks](locks.md).

## The rules kept in the database

With `rules: doctrine`, `DbalTokenRules` keeps one row per table. The form of a table's settings saves its rules:

```php
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;

$rules->save(TableRules::fromJson($table, $json));   // in place of those it had
$rules->remove($table);                             // the table hands out no tokens; its ledger stays
```

Every read checks the rules again, so that a row written by hand is refused with `InvalidTokenRule` rather than used.
