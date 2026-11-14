# Expiry

A currency created with a lifetime — `new Currency('stars', 'P1Y')` — makes each credit to a member expire that long
after its moment: points earned, granted, refunded or received. A currency without one never expires.

## First in first out

`Lots` follows what is left of each credit, the movements applied in the order the ledger wrote them:

- a debit that names a credit — the reversal of an earning, the expiry of a credit — takes from that credit first;
- every debit then takes from the oldest credits still alive at its moment, then from the oldest of the others;
- what no credit covers is a debt, which the next credits pay before they open a lot of their own.

## Expiring

```php
$points->expire($member);          // the default currency
$points->expire($member, 'stars');
$points->expireAll();              // every member, every currency with a lifetime
```

Each credit whose lifetime has ended with points left is expired by a movement `#expire:<credit key>` to
`expiry:expired`, dated at the end of its lifetime. Expiring again finds nothing more to expire. `expireAll()` is the
task a scheduler runs; it looks at the members credited before the cutoff of each currency, one by one. A spending and
a transfer expire the payer's credits first, so a member never spends points that have already expired.

## Spendings at the same moment

Each expiry is written by `Ledger::appendAfter()`: only if the account has no entry newer than the ones its lots were
read from, as one step that a spending of the same account cannot slip between. A spending that slipped in between
— one that took points from a credit a second before it expired — sends the expiry back to read the lots again, so
that the same points are never taken twice and the balance never falls below zero by an expiry. After
`Points::EXPIRY_ATTEMPTS` attempts that each found the account changed, `LedgerContention` reaches the caller, and
the expiry may be asked again.

## Checkpoints

The expiry does not read the whole history of the account each time. Once it has applied the entries, it keeps a
`LotsCheckpoint` in the ledger — the lots with points left, the debt, and the position they stand at — and the next
expiry, the one of each spending and transfer among them, reads only the entries written after that position. The
end of each lot kept is computed again from the currency's lifetime. When the lots no longer match the account's
balance — a movement before the position appeared late, or the derived movements were rebuilt —, the expiry reads the
whole history again and keeps a new checkpoint. A rebuild and the erasure of a member delete the checkpoints.
