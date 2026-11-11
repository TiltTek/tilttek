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
