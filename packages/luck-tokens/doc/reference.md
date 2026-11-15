# Reference

| Type | Role |
|---|---|
| `LuckTokens` | Grants, spends, passes and refreshes tokens; gives the balances and the ledger; erases a member |
| `TableRules` | The kinds of tokens of a table and its game masters; read from plain values or JSON |
| `TokenKind` | A kind of token: key, name, cap, scope, refresh, carry-over, grants, transfers |
| `HolderScope` | `Character` or `Player` |
| `Refresh` | `Never` or `EachSession` |
| `GrantPolicy` | `GameMasters` or `Anyone` |
| `Receipt` | The entries an operation wrote, the balance it left, and whether it was a replay |
| `Balance` | How many tokens of a kind a holder has |
| `Ledger\LedgerEntry` | One entry of the ledger: position, operation, type, kind, holder, change, balance, actor, time, reason, session |
| `Ledger\EntryType` | `Granted`, `Spent`, `TransferredOut`, `TransferredIn`, `Refreshed` or `SessionStarted` |
| `Event\TokensGranted` | Tokens granted |
| `Event\TokensSpent` | Tokens spent |
| `Event\TokensTransferred` | Tokens passed from one holder to another |
| `Event\SessionStarted` | A session started, with its refreshes |
| `Event\HolderErased` | A member erased under a pseudonym, with the number of entries rewritten |
| `Contract\TokenRules` | The rules of the tables |
| `Contract\TokenLedger` | Where the ledgers are kept, with their indexes; erases a member under a pseudonym |
| `Contract\HolderAccess` | Whether an actor controls the tokens of a holder |
| `Store\InMemoryTokenRules` | The rules in memory |
| `Store\InMemoryTokenLedger` | The ledgers in memory |
| `Store\SelfAccess` | Each holder controls their own tokens |

`Instant` is internal: the one way the library writes a time, in UTC.

## Exceptions

| Exception | Thrown when |
|---|---|
| `Exception\LuckTokensException` | The interface of every exception of the library |
| `Exception\InvalidTokenRule` | A kind, rules, an amount, an identifier, a reason or a ledger entry the library refuses |
| `Exception\TokenRefused` | An operation the rules do not allow: a table or a kind not known, an actor who may not, the cap, a balance too low, a transfer not allowed, an identifier reused, a session started already, a ledger full |
| `Exception\LedgerConflict` | Other writers kept the ledger busy three times in a row |

Nothing is written when an exception is thrown. Each message says what was expected, as
`member:8 holds 0 "fate"; 1 cannot be spent nor given.`
