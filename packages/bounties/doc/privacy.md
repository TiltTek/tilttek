# Privacy

## Erasing a member

`erase()` takes a member out of every bounty that names them — as sponsor or claimant —, a bounded batch at a time:

```php
while ($bounties->erase($member) > 0) {
    // 100 bounties a call by default; erase($member, 500) for the largest batch
}
```

In each bounty, two moves follow one another:

1. The member leaves. The open bounty they sponsor is settled at once: awarded when they awarded something — the
   awards paid, the rest given back to them —, expired when it has claims and no award, cancelled otherwise. Their
   claims on an open bounty are withdrawn, with the awards given to those claims, which are not paid: those points
   stay for the sponsor to award. This move is settled in the escrow and announced like any other. A bounty settled
   earlier whose escrow failed then is settled now, before anyone is replaced.
2. A pseudonym replaces them, silently: a reference of the kind `erased`, `Pseudonym::KIND`, whose identifier is drawn
   at random for each bounty. The sponsor or the claimant of their claims names the pseudonym, and the evidence of
   their claims is replaced by it too. Two bounties of one erased member do not share a pseudonym, so their records
   are not linked.

What other members did stays: their claims and their awards. A settled bounty keeps its outcome, and what was paid
stays paid.

A bounty whose sponsor or a winner is a pseudonym was settled before the erasure; `settle()` leaves it as it is, so
that no point is paid twice or to a pseudonym.

## References refused

Only `erase()` draws references of the kind `erased`. Every reference a caller gives — the sponsor of `offer()`,
`award()`, `close()` and `cancel()`, the claimant of `claim()`, the member of `erase()` — and the subject of a
`BountyDraft` is refused with `InvalidBounty` when its kind is `erased`, before anything is read, written or moved in
the escrow:

```php
try {
    $bounties->claim('b1', 'answer-1', $member, 'answer:901');
} catch (InvalidBounty $refused) {
    // The sponsor of a bounty is erased:…, a pseudonym the library draws for an erased member; it is never taken from a
    // caller.
}
```

A pseudonym read from a bounty therefore never acts in it: it cannot claim, award, close, cancel, offer the same
bounty again to get it back, or be erased again. Do not give members references of the kind `erased`.

## Order of the erasure

Erase the member's bounties before their points: points given back to the sponsor reach their account, which the
erasure of `tilttek/points` then takes care of.

## What the store does

`BountyStore::involving($member, $limit)` lists the identifiers of the bounties that name the member, `$limit` at
most: see [Writing a store](extension-points.md#writing-a-store).

A bounty another writer keeps changing throws `BountyConflict` after three tries; what was erased stays erased, and
the next call takes up the rest.
