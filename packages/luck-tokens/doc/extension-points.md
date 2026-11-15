# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Contract\TokenRules` | The rules of each table: from its settings, a database | `Store\InMemoryTokenRules` |
| `Contract\TokenLedger` | The storage of the ledgers | `Store\InMemoryTokenLedger` |
| `Contract\HolderAccess` | Who controls the tokens of whom: a player those of their character | `Store\SelfAccess` |
| `Psr\EventDispatcher\EventDispatcherInterface` | Where the events go | none: nothing is dispatched |
| `Psr\Clock\ClockInterface` | The time of the entries | `Symfony\Component\Clock\NativeClock` |

Every point is passed to the constructor of `LuckTokens`, which keeps nothing between two calls.

## Writing a ledger

A `TokenLedger` keeps the entries of each table in the order of their positions, from 1, and never removes one; the one
change it makes to an entry is the erasure of a member. What every implementation does:

- `append($table, $after, $entries)` writes the entries of one operation, all or none, if and only if the last
  position of the table is still `$after`; otherwise it throws `LedgerConflict` and writes nothing. In a database, a
  unique key on the table and the position, inside one transaction, gives exactly that: the second writer's insert
  fails.
- It refuses, with `InvalidTokenRule`, an entry of another table or not numbered from `$after + 1`.
- `last()` gives the last position, 0 for none.
- `balance()` gives the balance of the holder's last entry of the kind, 0 for none; an index on the table, the kind,
  the holder and the position answers it without reading the ledger.
- `holders()` lists the holders of the entries that have a kind, in the order they first appear.
- `operation()` gives the entries of an operation; `hasSession()` whether the start of a session is there.
- `entries()` lists every entry, for the audit.
- `eraseHolder($member, $pseudonym)` writes the pseudonym in place of the member, as holder and as actor, in every entry
  of every table, and in the balances and holders it indexes, all or none; the positions, changes, balances and order
  stay. It returns the number of entries rewritten, each counted once, 0 when none names the member, and refuses a
  pseudonym equal to the member with `InvalidTokenRule`. In a database, two updates in one transaction — the holder
  column, then the actor column — give exactly that, with an index on each.

`LedgerEntry::toArray()` gives the row to keep and `LedgerEntry::fromArray()` reads it back, checked. The memory
ledger's tests, in `tests/Unit/Store/StoresTest.php` and `tests/Unit/ErasureTest.php`, hold these behaviours.

## The events

Each event is dispatched once its entries are in the ledger, never for an operation sent again:

| Event | Carries |
|---|---|
| `Event\TokensGranted` | The entry, and `amount()` |
| `Event\TokensSpent` | The entry, and `amount()`, always positive |
| `Event\TokensTransferred` | The entries out and in, and `amount()` |
| `Event\SessionStarted` | The entry of the start, and the refreshes |
| `Event\HolderErased` | The pseudonym and the number of entries rewritten; never the member nor a table |

A listener that offers a reroll when a token is spent reads the kind and the reason of `TokensSpent::$entry`. A
listener that pushes the change to the table through Messenger sends the table, the operation and the position — the
entry is read back from the ledger by the handler.
