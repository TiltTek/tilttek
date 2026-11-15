# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Contract\ChallengeStore` | Where the challenges are kept: a table of records, a document store | `Store\InMemoryChallengeStore` |
| `Contract\PrizeEscrow` | Where the prizes are held: see [Escrow](escrow.md) | `Escrow\PointsPrizeEscrow`, `Escrow\InMemoryPrizeEscrow` |
| `Contract\Friendship` | Who is a friend of whom, from the site's social graph | `Store\InMemoryFriendship` |
| `Contract\Moderation` | Where reports go, and who moderates: see [Moderation](moderation.md) | `Store\InMemoryModeration` |
| `ChallengeRules` | How long a challenge lasts, its review, its largest prize | its defaults |
| `Psr\EventDispatcher\EventDispatcherInterface` | Where the events go | none: nothing is dispatched |
| `Psr\Clock\ClockInterface` | The time of each move | `Symfony\Component\Clock\NativeClock` |

Every point is passed to the constructor of `FriendChallenges`, which keeps nothing between two calls.

## Writing a store

A `ChallengeStore` keeps one record a challenge, `Challenge::toArray()`, and gives it back through
`Challenge::fromArray()`. What every implementation does:

- `get($id)` gives the challenge, or null.
- `add($challenge)` keeps a new challenge, and throws `ChallengeConflict` when one of the same identifier is kept.
- `update($challenge, $expected)` keeps the challenge at its next version if and only if the kept one is still at
  version `$expected`, and otherwise throws `ChallengeConflict` and writes nothing. In a database, an `UPDATE … WHERE
  id = ? AND version = ?` that counts the rows it changed gives exactly that.
- `due($at, $limit)` lists the identifiers of the open challenges whose `review_ends_at` is at or before `$at`, the
  earliest first, `$limit` at most; an index on the state and `review_ends_at` answers it.
- `involving($member, $limit)` lists the identifiers of the challenges that name the member — see
  `Challenge::involves()`: the creator, the invitees and the participants —, `$limit` at most, for its
  [erasure](privacy.md). A table of the members of each challenge, written with the record, answers it.

Keep the times of the record as the UTC texts it holds, or in UTC columns. When the challenges and the escrow live in
the same database, keep the creation of a challenge and its hold in one transaction.

## The events

Each event is dispatched once its move is kept and settled, never for a move made again:

| Event | When | Carries |
|---|---|---|
| `Event\ChallengeCreated` | A challenge is created and its prize held | The challenge, its creator |
| `Event\MemberInvited` | Once for each friend invited, at the creation | The challenge, the member |
| `Event\ParticipantJoined` | A member takes part | The challenge, the member |
| `Event\ProofSubmitted` | A participant submits a proof | The challenge, the proof, the participant |
| `Event\ProofDecided` | A proof is accepted, rejected or removed | The challenge, the proof, its state |
| `Event\ChallengeWon` | A proof is accepted and the prize paid | The challenge, the winner, the proof, the prize, its unit |
| `Event\ChallengeEnded` | A challenge is cancelled, expires or is removed, and its prize given back | The challenge, its state |
| `Event\ChallengeFrozen` | A moderator freezes a challenge | The challenge |
| `Event\ChallengeUnfrozen` | A moderator unfreezes a challenge | The challenge |

The events carry identifiers only. A listener that notifies a member, pushes the page through Mercure or sends a
Messenger message sends those identifiers, and reads the rest from the store.
