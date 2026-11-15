# Privacy

## Erasing a member

`erase()` takes a member out of every challenge that names them — as creator, invitee, participant, author of a proof,
voter or winner —, a bounded batch at a time:

```php
while ($challenges->erase($member) > 0) {
    // 100 challenges a call by default; erase($member, 500) for the largest batch
}
```

In each challenge, two moves follow one another:

1. The member leaves. The open or frozen challenge they created is removed, and its prize given back to them; their
   proofs waiting for a review in an open or frozen challenge are removed, so that they cannot win it. This move is
   settled in the escrow and announced like any other: `ChallengeEnded` for a challenge removed, `ProofDecided` for a
   proof removed. A challenge settled earlier whose escrow failed then is settled now, before anyone is replaced.
2. A pseudonym replaces them, silently: a reference of the kind `erased`, `Pseudonym::KIND`, whose identifier is drawn
   at random for each challenge. Every place the challenge named them — creator, invitees, those who declined,
   participants, proofs and their votes, winner — names the pseudonym, and the evidence of their proofs is replaced by
   it too. Two challenges of one erased member do not share a pseudonym, so their records are not linked.

What other members did stays: their proofs, their votes and their wins, the votes the erased member gave included, as
counts. A won challenge keeps its outcome, and what was paid stays paid. The title and the terms of a challenge stay:
a site that wants them gone removes the challenge before the erasure.

A challenge whose creator or winner is a pseudonym was settled before the erasure; `settle()` leaves it as it is, so
that no point is paid twice or to a pseudonym.

## References refused

Only `erase()` draws references of the kind `erased`. Every reference a caller gives is refused with
`InvalidChallenge` when its kind is `erased`, before anything is read, written, reported or moved in the escrow:

| Method | Reference checked |
|---|---|
| `create()` | the creator, and each invitee of the `ChallengeDraft` |
| `join()`, `submitProof()` | the participant |
| `decline()` | the invitee |
| `approve()`, `reject()` | the judge: the creator, a voter or a moderator |
| `cancel()` | the creator |
| `report()` | the author of the report |
| `freeze()`, `unfreeze()`, `remove()`, `removeProof()` | the moderator, before the site's `Moderation` is asked |
| `erase()` | the member to erase |

```php
try {
    $challenges->join('c1', $member);
} catch (InvalidChallenge $refused) {
    // A participant is erased:…, a pseudonym the library draws for an erased member; it is never taken from a caller.
}
```

`ChallengeDraft` itself takes invitees of the kind `erased`, because it also reads back stored challenges whose
invitees were erased since; `create()` refuses them. A pseudonym read from a challenge therefore never acts in it: it
cannot join, prove, judge, cancel, report, moderate, create the same challenge again to get it back, or be erased
again. Do not give members references of the kind `erased`.

## Order of the erasure

Erase the member's challenges before their points: a prize given back to its creator reaches their account, which the
erasure of `tilttek/points` then takes care of. The reports the member sent go to the site's moderation, and the
friendships come from the site's social graph: the site erases those.

## What the store does

`ChallengeStore::involving($member, $limit)` lists the identifiers of the challenges that name the member, `$limit` at
most: see [Writing a store](extension-points.md#writing-a-store).

A challenge another writer keeps changing throws `ChallengeConflict` after three tries; what was erased stays erased,
and the next call takes up the rest.
