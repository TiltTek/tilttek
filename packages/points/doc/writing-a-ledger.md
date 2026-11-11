# Writing a ledger

An application that keeps its points in its own storage implements `Tilttek\Points\Ledger\Ledger`:

| Method | Contract |
|---|---|
| `append(Movement, DateTimeImmutable $at): Recorded` | Writes the movement at the next position, or returns the entry already there under its key (`new` false); the same key for another movement raises `MovementConflict`. Two processes writing the same key at once write it once. |
| `appendCovered(Movement, DateTimeImmutable $at): Recorded` | Like `append()`, but only when the debited account's balance covers the amount, checked and written as one step; otherwise `InsufficientPoints`. A duplicate returns the entry without checking again. |
| `find(MovementKey): ?LedgerEntry` | The entry of the key. |
| `causedBy(string $cause): list<LedgerEntry>` | The entries of an activity key, by position. |
| `balance(Account, string $currency): int` | Credits less debits of the account in the currency. |
| `net(Account $to, Account $from, string $currency, ?since, ?until): int` | What `to` received from `from` less what it gave back, over the moments from `since`, included, to `until`, excluded. |
| `statement(Account, ?string $currency, int $after, int $limit): list<LedgerEntry>` | The account's entries after a position, by position; `Reading::check()` refuses a negative position or a limit below 1. |
| `membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): list<Account>` | The members' accounts credited at or before the cutoff, each once, sorted. |
| `forgetDerived(?DateTimeImmutable $since): int` | Deletes the earned, reversed and expired movements from `since` on, or all of them. |
| `eraseMember(ResourceReference): Erasure` | Deletes the member's movements with the ledger's own accounts, writes `erased:member` in place of the member in the others. |

Positions only grow; they need not follow each other. The test case `Tilttek\Points\Tests\Unit\Ledger\LedgerTestCase`
states the contract in tests: extend it, build your ledger in `ledger()`, and it runs against your storage.
