# The ledger

## Accounts

Every movement goes from one `Account` to another. The account's type says what it stands for:

| Type | Named by | Written as | Its balance |
|---|---|---|---|
| `Member` | the member's reference | `member:member:44` | The member's points |
| `Issuance` | the rule or the source that issues | `issuance:games`, `issuance:manual` | Negative by what it gave |
| `Redemption` | what the points pay for | `redemption:reward:12` | What was spent on it |
| `Expiry` | — | `expiry:expired` | What expired |
| `Erased` | — | `erased:member` | What erased members gave or received from others |

`Account::fromString()` reads back what `toString()` wrote. A name other than a member's is 1 to 255 lower-case
letters, digits, `_`, `.`, `:` or `-`, starting with a letter or a digit.

## Movements

A `Movement` moves 1 to 10^15 points of one currency from its debited account to its credited account, which differ.
Besides its key and its kind, it carries:

- `occurredAt`, the moment it counts at: the activity's moment for points earned and reversed, the end of a credit's
  lifetime for points expired, the moment it was asked for otherwise;
- `cause`, the idempotency key of the activity that earned or reversed the points;
- `reverses`, the key of the movement it undoes — the earning a reversal cancels, the spending a refund gives back —
  or of the credit it expires.

| Kind | From | To | Written by |
|---|---|---|---|
| `Earned` | the rule's issuance account | the member | `PointsProjection` |
| `Reversed` | the member | the rule's issuance account | `PointsProjection`, on a cancellation |
| `Expired` | the member | `expiry:expired` | `Points::expire()` |
| `Granted` | a source's issuance account | the member | `Points::grant()` |
| `Revoked` | the member | a source's issuance account | `Points::revoke()` |
| `Spent` | the member | a redemption account | `Points::spend()` |
| `Refunded` | the redemption account | the member | `Points::refund()` |
| `Transferred` | a member | another member | `Points::transfer()` |

The first three are *derived*: they follow from the activity and the balances, and a rebuild recomputes them. The
others are *decided*, and nothing recomputes them.

## Keys

A `MovementKey` makes a movement written once: the same movement written again under its key returns the entry
already there, and the same key for another movement — another kind, currency, account or amount — raises
`MovementConflict`. The moment is left out of the comparison, so an operation asked again later is still a duplicate.

The application gives the key of each operation it decides, built from what makes the operation unique —
`order:2026-0193:redeem`, `gift:2026-11-07:44:12` — never from the time it runs. The keys the library derives start with
`#`, which the application's keys cannot start with: `#earn:<rule>:<activity key>`, `#reverse:<earning key>`,
`#expire:<credit key>`, `#refund:<spending key>`. A key is 1 to 400 bytes, without whitespace or control characters.

## Balances and histories

```php
$points->balance($member);            // in the default currency
$points->balance($member, 'stars');
$points->history($member);            // the 50 first movements, oldest first
$points->history($member, 'stars', after: 120, limit: 100);
```

A balance is the sum of the account's movements in the currency: what it was credited less what it was debited. A
history is read by position, at most 1,000 movements at a time; `LedgerEntry::toArray()` gives each line as plain
values, and `Movement::amountFor($account)` the signed amount for the member.
