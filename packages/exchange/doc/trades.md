# Trades

When the application allows it, members trade items of their collections — virtual cards, the dice of a set —, and
may add points on either side.

## The inventory

The items live in the application's collections. `Trades` reaches them through an `Inventory` the application
implements: who owns an item, whether it may change hands — an item earned by a quest may stay bound to its member —,
and the moves of an accepted trade, all of them or none. `InMemoryInventory` keeps items in memory, for tests and for
trying trades out.

## An offer

`Trades::offer($key, $proposer, $recipient, $offered, $requested, $offeredPoints, $requestedPoints, $currency)` makes
an offer: the proposer's items for the recipient's, at least one item in all, at most 50 on each side, no item twice.
The guards are checked ([Guards](guards.md)), each item must belong to its giver and be tradable, and each offered item
must be free: from the offer on, the store holds the offered items, which no other offer can offer until this one
closes. The requested items are not held; several offers may ask for the same item.

An offer waits for its answer for its lifetime, seven days unless `Trades` is given another ISO 8601 duration.

## The answers

- **Accept**, by the recipient: ownership, tradability and holds are checked again, and both balances against the
  points they add; then, in one transaction, the offer closes, the points move by transfers of the ledger under
  `exchange:offer:<key>:offered` and `:requested`, and the inventory moves the items. When a step fails, nothing moves.
- **Decline**, by the recipient, and **withdraw**, by the proposer: the offer closes and its items are free again.
- **Counter**, by the recipient: an offer of their own to the proposer, which names the offer it answers; in one
  transaction, the first offer closes as countered and the counter-offer opens. It is checked as an offer before the
  first one closes.

A member who is not the right side is refused with `Refusal::NotParty`; an answer to an offer no longer open is refused
with `ExchangeClosed`, and an answer after the lifetime expires the offer first. The same answer given twice returns
the offer as it is; the same key for another offer is an `ExchangeConflict`.

```php
$trades->offer('trade:812', $ada, $grace, offered: [$redDragon], requested: [$greenElf], offeredPoints: 20);
$trades->counter('trade:812', $grace, 'trade:813', offered: [$greenElf], requested: [$redDragon, $blueKnight]);
$trades->accept('trade:813', $ada);
```

## Expiry and the journal

`expireDue()` expires the offers past their lifetime and frees their items: the task a scheduler runs.
`ofMember($member)` gives the offers a member made or was made, in the order they were made: the journal of their
trades. `made($member)` counts the offers a member made over the allowance's period.

## One transaction

The steps of an acceptance and a counter-offer run through a `Transaction`. `ImmediateTransaction` runs them as they
come: with the stores in memory, `Trades` checks what it can before its first write. A store on a database runs them
in one of its transactions — the bundle's `DbalTransaction` does —, and the ledger of points and the inventory take
part when they are kept on the same connection.
