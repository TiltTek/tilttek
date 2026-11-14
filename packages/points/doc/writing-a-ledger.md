# Writing a ledger

An application that keeps its points in its own storage implements `Tilttek\Points\Ledger\Ledger`:

| Method | Contract |
|---|---|
| `append(Movement, DateTimeImmutable $at): Recorded` | Writes the movement at the next position, or returns the entry already there under its key (`new` false); the same key for another movement raises `MovementConflict`. Two processes writing the same key at once write it once. |
| `appendCovered(Movement, DateTimeImmutable $at): Recorded` | Like `append()`, but only when the debited account's balance covers the amount, checked and written as one step; otherwise `InsufficientPoints`. A duplicate returns the entry without checking again. |
| `appendAfter(Movement, DateTimeImmutable $at, int $after): ?Recorded` | Like `append()`, but only when the debited account has no entry in the movement's currency after the position, checked and written as one step that `appendCovered()` for the same account cannot slip between; otherwise null, writing nothing. A duplicate returns the entry without checking. |
| `find(MovementKey): ?LedgerEntry` | The entry of the key. |
| `causedBy(string $cause): list<LedgerEntry>` | The entries of an activity key, by position. |
| `balance(Account, string $currency): int` | Credits less debits of the account in the currency. |
| `net(Account $to, Account $from, string $currency, ?since, ?until): int` | What `to` received from `from` less what it gave back, over the moments from `since`, included, to `until`, excluded. |
| `statement(Account, ?string $currency, int $after, int $limit): list<LedgerEntry>` | The account's entries after a position, by position; `Reading::check()` refuses a negative position or a limit below 1. |
| `membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): list<Account>` | The members' accounts credited at or before the cutoff, each once, sorted. |
| `keptLots(Account, string $currency): ?LotsCheckpoint` | The checkpoint of the account's lots in the currency last kept, or null. |
| `keepLots(LotsCheckpoint): void` | Keeps the checkpoint in place of the one kept, unless that one stands at the same position or a later one. |
| `forgetDerived(?DateTimeImmutable $since): int` | Deletes the earned, reversed and expired movements from `since` on, or all of them, and every checkpoint of lots. |
| `eraseMember(ResourceReference): Erasure` | Deletes the member's movements with the ledger's own accounts and the checkpoints of the member's lots, writes `erased:member` in place of the member in the others. |

Positions only grow; they need not follow each other. A checkpoint is stored as it comes, or as the plain values of
`LotsCheckpoint::toArray()`, read back by `LotsCheckpoint::fromArray()`. The test case `Tilttek\Points\Tests\Unit\Ledger\LedgerTestCase`
states the contract in tests: extend it, build your ledger in `ledger()`, and it runs against your storage.
