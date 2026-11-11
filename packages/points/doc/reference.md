# Reference

| Type | Role |
|---|---|
| `Points` | Balances, histories, grants, revocations, spendings, refunds, transfers, expiry, erasure |
| `PointsProjection` | The activity turned into points, cancellations reversed, rebuilds |
| `Account`, `AccountType` | An account of the ledger and its type |
| `Currency`, `Currencies` | A currency of points and its lifetime; the currencies of an application |
| `MovementKey` | What makes a movement written once |
| `MovementKind` | Why points moved; the derived kinds |
| `Movement` | Points from a debited account to a credited one |
| `LedgerEntry` | A movement at its position, and when it was written |
| `Recorded` | An entry and whether this call wrote it |
| `Erasure` | What erasing a member deleted and detached |
| `Lot`, `Lots` | What is left of each credit, first in first out |
| `Ledger\Ledger` | Where the ledger is kept |
| `Ledger\InMemoryLedger` | The ledger in memory |
| `Ledger\Reading` | The bounds of a read |
| `Rule\EarningRule` | Points for a gesture |
| `Rule\Cap`, `Rule\Period` | The most a rule gives a member over a period |
| `Rule\Rules` | The rules by name and their timezone |
| `Exception\PointsException` and its five exceptions | [Exceptions](exceptions.md) |
