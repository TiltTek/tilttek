# Locks

The ledger already refuses two writers after the same position, and the library reads it again, three times in all.
That is enough for most tables; when many players spend at the same moment, the retries can run out and the last one
reaches the caller as `LedgerConflict`. A Symfony lock around each operation makes them wait for one another instead.

```yaml
framework:
    lock: '%env(LOCK_DSN)%'     # a store every process shares: Redis, a database, flock on one machine

tilttek_luck_tokens:
    lock:
        enabled: true
        scope: holder           # or table
        ttl: 30.0
```

The bundle then registers `Tilttek\LuckTokensBundle\Application\LockedLuckTokens`, public, with the operations of
`LuckTokens` under the same names and arguments. Inject it where the application writes; read the balances and the
ledger through `LuckTokens` as before.

```php
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;

public function __construct(private LockedLuckTokens $tokens) {}

$this->tokens->spend($table, 'fate', $member, 1, $member, $operation, 'Reroll');
```

## What a lock covers

| Operation | `holder` scope | `table` scope |
|---|---|---|
| `grant()`, `spend()` | the holder | the table |
| `transfer()` | both holders, in the order of their references | the table |
| `startSession()` | the table | the table |
| `eraseHolder()` | the member | the member |

The keys are `tilttek_luck_tokens.holder.<reference>` and `tilttek_luck_tokens.table.<reference>`. A transfer takes
the locks of its two holders always in the same order, so that two transfers between the same holders, one each way,
never hold one lock each while waiting for the other. Each lock is taken waiting, held for the operation alone, and
released even when the operation is refused.

- `holder` serializes what one holder does — a double click with two identifiers, two tabs — and leaves the others
  free; two holders of the same table may still meet in the ledger, and the library reads it again.
- `table` serializes every operation of a table: no two writers ever meet in its ledger, at the cost of waiting for one
  another.

The ledger's own check stays in both: a lock that expires — its `ttl` passed —, or a writer that does not go through
`LockedLuckTokens`, still meets `LedgerConflict` and its retries. The tests run four processes granting to the same
holder at once under the locks of `flock`: none meets another in the ledger.

The store must be shared by every process that writes: `flock` and `semaphore` on one machine only, Redis, PostgreSQL
or a PDO store across machines. The service keeps nothing between two calls.
