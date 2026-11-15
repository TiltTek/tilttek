# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Contract\BountyStore` | Where the bounties are kept: a table of records, a document store | `Store\InMemoryBountyStore` |
| `Contract\BountyEscrow` | Where the points are held: see [Escrow](escrow.md) | `Escrow\PointsBountyEscrow`, `Escrow\InMemoryBountyEscrow` |
| `BountyRules` | The length, the grace, the smallest and largest bounty, the open bounties a sponsor | its defaults |
| `Psr\EventDispatcher\EventDispatcherInterface` | Where the events go | none: nothing is dispatched |
| `Psr\Clock\ClockInterface` | The time of each move | `Symfony\Component\Clock\NativeClock` |

Every point is passed to the constructor of `Bounties`, which keeps nothing between two calls.

## Writing a store

A `BountyStore` keeps one record a bounty, `Bounty::toArray()`, and gives it back through `Bounty::fromArray()`. What
every implementation does:

- `get($id)` gives the bounty, or null.
- `openOn($subject)` gives the identifier of the open bounty on the subject, or null.
- `openBy($sponsor)` counts the open bounties the member sponsors; an index on the sponsor and the state answers it.
- `add($bounty)` keeps a new bounty, and throws `BountyConflict` when one of the same identifier, or an open one on
  the same subject, is kept. In a database, a unique key on the identifier and a unique partial index on the subject of
  the open bounties give exactly that.
- `update($bounty, $expected)` keeps the bounty at its next version if and only if the kept one is still at version
  `$expected`, and otherwise throws `BountyConflict` and writes nothing: an `UPDATE … WHERE id = ? AND version = ?` that
  counts the rows it changed.
- `due($at, $limit)` lists the identifiers of the open bounties whose `grace_ends_at` is at or before `$at`, the
  earliest first, `$limit` at most; an index on the state and `grace_ends_at` answers it.
- `involving($member, $limit)` lists the identifiers of the bounties that name the member — see `Bounty::involves()`:
  the sponsor and the claimants —, `$limit` at most, for its [erasure](privacy.md). A table of the members of each
  bounty, written with the record, answers it.

Keep the times of the record as the UTC texts it holds, or in UTC columns. When the bounties and the escrow live in
the same database, keep the offer of a bounty and its hold in one transaction.

## The events

Each event is dispatched once its move is kept and settled, never for a move made again:

| Event | When | Carries |
|---|---|---|
| `Event\BountyOffered` | A bounty is offered and its points held | The bounty, its sponsor, its subject |
| `Event\BountyClaimed` | A member claims a bounty | The bounty, the claim, the claimant |
| `Event\AwardGiven` | The sponsor awards points to a claim | The bounty, the award, the claim, the points |
| `Event\BountyAwarded` | Once for each winner, when the bounty is settled and the points paid | The bounty, the winner, the points, their unit |
| `Event\BountyClosed` | A bounty is settled — awarded, expired or cancelled | The bounty, its state, the points given back |

The events carry identifiers only. A listener that notifies a member, pushes the page through Mercure or sends a
Messenger message sends those identifiers, and reads the rest from the store.
