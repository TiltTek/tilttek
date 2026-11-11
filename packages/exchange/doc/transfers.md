# Transfers

When the application allows it, members give each other points: `Transfers::transfer($from, $to, $amount, $key,
$currency)` checks the guards ([Guards](guards.md)), moves the points by a transfer of the ledger under
`exchange:transfer:` and the key — which never makes the giver's balance negative —, then writes the transfer to the
journal.

```php
$transfers->transfer($member, $friend, 50, 'gift:2026-11-07:44');
```

The same key gives once: asked again, the transfer already written is returned, the guards and the clock aside; the
same key for another transfer is an `ExchangeConflict`.

## The journal

The `TransferLog` keeps every transfer: `journal($limit)` gives the latest of every member, newest first, 50 unless
asked otherwise and at most 1000 — what a moderator examines —; `ofMember($member)` gives those a member gave or
received, oldest first; `given($member, $currency)` gives what a member gave over the allowance's current period, or
ever without an allowance.
