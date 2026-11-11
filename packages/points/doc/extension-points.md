# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Ledger\Ledger` | The storage of the ledger, in place of `InMemoryLedger` | [Writing a ledger](writing-a-ledger.md) |
| `Psr\Clock\ClockInterface` | The clock that stamps each movement and decides expiry: the system's in production, a fixed one in tests | [Testing](testing.md) |

The currencies and the earning rules are values: `Currencies::fromArray()` and `Rules::fromArray()` read them from a
configuration, or the application builds them in code. Every point is passed to the constructor of the service that uses
it; the services keep no state of their own between two calls.
