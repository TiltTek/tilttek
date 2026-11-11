# Moving points

`Tilttek\Points\Points` is a public service, autowirable:

```php
$points->balance($member);                                    // the default currency
$points->history($member, 'stars', after: 0, limit: 50);
$points->grant($member, 100, 'contest:2026:44', 'contest:2026');
$points->spend($member, 300, 'reward:12:44', 'reward:12');
$points->refund('reward:12:44');
$points->transfer($giver, $receiver, 10, 'gift:2026-11-07:44:12');
```

- **Keys** are the application's: the same call made twice moves the points once. A key starting with `#` is refused,
  since the library's own keys start with it.
- **A spending or a transfer** expires the payer's credits due, then checks the balance and writes the movement under
  a lock; `InsufficientPoints` says the balance and the amount.
- **A refund** gives back a spending once, as a new credit.
- **Who may give to whom**, and how much a reward costs, are the application's decisions, made before the call.

The library's documentation explains the ledger, the kinds of movement and the expiry in full.
